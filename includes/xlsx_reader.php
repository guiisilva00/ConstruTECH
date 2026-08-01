<?php
/**
 * Leitor mínimo de arquivos .xlsx, sem dependências externas
 * (usa apenas as extensões padrão ext-zip e ext-simplexml).
 *
 * Um .xlsx é um .zip contendo XML. Este leitor:
 *  1. Resolve qual XML é a primeira planilha via workbook.xml + rels
 *     (não assume "sheet1.xml" — arquivos exportados pelo Google Sheets
 *     às vezes nomeiam de forma diferente).
 *  2. Resolve sharedStrings.xml (texto fica indexado ali, não inline).
 *  3. Devolve um array de linhas; cada linha é um array de valores
 *     na ordem das colunas (A, B, C...), preenchendo com '' células
 *     puladas (o XLSX omite células vazias no XML).
 *
 * Limitações conscientes (fora do escopo deste caso de uso):
 *  - Lê apenas a primeira planilha do arquivo.
 *  - Não interpreta fórmulas (usa o valor em cache, se houver).
 *  - Não converte datas/números formatados — tudo volta como string/num cru.
 */

final class XlsxReadException extends RuntimeException {}

final class XlsxReader
{
    private const MAX_ENTRY_BYTES = 30 * 1024 * 1024; // 30MB por entrada do zip (anti zip-bomb)

    /**
     * @return array<int, array<int, string>> linhas (0-indexed), cada uma com as colunas (0-indexed)
     */
    public static function read(string $filePath, int $maxRows = 5000): array
    {
        if (!is_file($filePath)) {
            throw new XlsxReadException('Arquivo não encontrado.');
        }

        $zip = new ZipArchive();
        if ($zip->open($filePath) !== true) {
            throw new XlsxReadException('O arquivo não é um .xlsx válido (não foi possível abrir como ZIP).');
        }

        try {
            if ($zip->locateName('xl/workbook.xml') === false) {
                throw new XlsxReadException('O arquivo não parece ser uma planilha .xlsx (estrutura inesperada).');
            }

            $sharedStrings = self::lerSharedStrings($zip);
            $sheetPath     = self::resolverPrimeiraPlanilha($zip);
            $xml           = self::lerXmlSeguro($zip, $sheetPath);

            return self::extrairLinhas($xml, $sharedStrings, $maxRows);
        } finally {
            $zip->close();
        }
    }

    private static function lerXmlSeguro(ZipArchive $zip, string $entryName): SimpleXMLElement
    {
        $stat = $zip->statName($entryName);
        if ($stat === false) {
            throw new XlsxReadException("Entrada '$entryName' não encontrada no arquivo.");
        }
        if ($stat['size'] > self::MAX_ENTRY_BYTES) {
            throw new XlsxReadException('Arquivo XLSX excede o tamanho seguro de processamento.');
        }

        $conteudo = $zip->getFromName($entryName);
        if ($conteudo === false) {
            throw new XlsxReadException("Não foi possível ler '$entryName' do arquivo.");
        }

        $anterior = libxml_use_internal_errors(true);
        // LIBXML_NONET: bloqueia acesso à rede durante o parse (proteção extra contra XXE/SSRF)
        $xml = simplexml_load_string($conteudo, 'SimpleXMLElement', LIBXML_NONET);
        libxml_use_internal_errors($anterior);

        if ($xml === false) {
            throw new XlsxReadException("XML inválido em '$entryName'.");
        }

        return $xml;
    }

    /** @return array<int, string> índice numérico -> texto */
    private static function lerSharedStrings(ZipArchive $zip): array
    {
        if ($zip->locateName('xl/sharedStrings.xml') === false) {
            return [];
        }

        $xml = self::lerXmlSeguro($zip, 'xl/sharedStrings.xml');
        $xml->registerXPathNamespace('a', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');

        $strings = [];
        foreach ($xml->si as $si) {
            // <si><t>texto</t></si>  OU  <si><r><t>parte1</t></r><r><t>parte2</t></r></si>
            if (isset($si->t)) {
                $strings[] = (string) $si->t;
            } else {
                $partes = [];
                foreach ($si->r as $run) {
                    $partes[] = (string) $run->t;
                }
                $strings[] = implode('', $partes);
            }
        }

        return $strings;
    }

    private static function resolverPrimeiraPlanilha(ZipArchive $zip): string
    {
        $workbook = self::lerXmlSeguro($zip, 'xl/workbook.xml');
        $workbook->registerXPathNamespace('w', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
        $workbook->registerXPathNamespace('r', 'http://schemas.openxmlformats.org/officeDocument/2006/relationships');

        $primeiraSheet = $workbook->xpath('//w:sheets/w:sheet[1]');
        if (empty($primeiraSheet)) {
            return 'xl/worksheets/sheet1.xml'; // fallback razoável
        }

        $rId = (string) $primeiraSheet[0]->attributes('r', true)->id;

        if ($zip->locateName('xl/_rels/workbook.xml.rels') === false || $rId === '') {
            return 'xl/worksheets/sheet1.xml';
        }

        $rels = self::lerXmlSeguro($zip, 'xl/_rels/workbook.xml.rels');
        foreach ($rels->Relationship as $rel) {
            if ((string) $rel['Id'] === $rId) {
                $target = (string) $rel['Target'];
                $target = ltrim($target, '/');
                return str_starts_with($target, 'xl/') ? $target : 'xl/' . $target;
            }
        }

        return 'xl/worksheets/sheet1.xml';
    }

    private static function colunaParaIndice(string $ref): int
    {
        // Extrai as letras da referência da célula, ex: "AB12" -> "AB"
        preg_match('/^([A-Z]+)/', $ref, $m);
        $letras = $m[1] ?? 'A';

        $indice = 0;
        foreach (str_split($letras) as $letra) {
            $indice = $indice * 26 + (ord($letra) - ord('A') + 1);
        }
        return $indice - 1; // 0-indexed
    }

    /** @return array<int, array<int, string>> */
    private static function extrairLinhas(SimpleXMLElement $sheetXml, array $sharedStrings, int $maxRows): array
    {
        $sheetXml->registerXPathNamespace('w', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
        $linhas = [];

        foreach ($sheetXml->xpath('//w:sheetData/w:row') as $row) {
            if (count($linhas) >= $maxRows) {
                break;
            }

            $linha = [];
            foreach ($row->c as $celula) {
                $ref = (string) $celula['r'];
                $col = $ref !== '' ? self::colunaParaIndice($ref) : count($linha);
                $tipo = (string) $celula['t'];

                if ($tipo === 's') {
                    $idx = isset($celula->v) ? (int) $celula->v : -1;
                    $valor = $sharedStrings[$idx] ?? '';
                } elseif ($tipo === 'inlineStr') {
                    $valor = (string) ($celula->is->t ?? '');
                } else {
                    // número, string de fórmula (t="str") ou booleano — valor cru é suficiente aqui
                    $valor = isset($celula->v) ? (string) $celula->v : '';
                }

                $linha[$col] = trim($valor);
            }

            if (!$linha) {
                continue;
            }

            // Preenche colunas puladas (o XLSX não grava células vazias) para manter o alinhamento
            $maxCol = max(array_keys($linha));
            $linhaCompleta = [];
            for ($i = 0; $i <= $maxCol; $i++) {
                $linhaCompleta[$i] = $linha[$i] ?? '';
            }

            // Ignora linhas totalmente em branco
            if (implode('', $linhaCompleta) === '') {
                continue;
            }

            $linhas[] = $linhaCompleta;
        }

        return $linhas;
    }
}
