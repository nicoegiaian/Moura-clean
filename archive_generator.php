<?php
require_once 'constants.php';
require_once 'DatabaseConnector.php';
require_once 'vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class ArchiveGenerator
{
    private $db;
    private $fechaurl;

    function __construct($fechaurl)
    {
        $this->db = new DatabaseConnector(DB_SERVER, DB_PORT, DB_NAME, DB_USER, DB_PASSWORD);
        $this->fechaurl = $fechaurl;
        $this->ensureTableExists();
        $this->generateData();
        $this->createTXT();
        $this->createXLSX();
    }

    private function generateData()
    {
        $script = 'archivosdiariosPATCH.php';
        $fechaurl = $this->fechaurl;
        $command = PHP_BINARY . " " . escapeshellarg($script) . " " . escapeshellarg($fechaurl);
        $output = [];
        $return_var = 0;
        exec($command, $output, $return_var);

        if ($return_var !== 0) {
            echo "Advertencia: Error al ejecutar el script $script. Código de retorno: $return_var\n";
            echo "Salida: " . implode("\n", $output) . "\n";
            return false;
        }

        echo "output from $script:\n" . implode("\n", $output) . "\n";

        return true;
    }

    private function createTXT()
    {
        // Crear directorio si no existe
        if (!is_dir('./archivosFixed')) {
            mkdir('./archivosFixed', 0775, true);
        }

        $archivoMouraLiquidacion = fopen('./archivosFixed/LiquidacionesBancos' . DIVISION_BSAS . $this->fechaurl . '.txt', 'w');
        $fechaLiquidacion = DateTime::createFromFormat('dmy', $this->fechaurl)->format('Y-m-d');
        $lineasLiquidacion = $this->obtenerLineasLiquidacion($fechaLiquidacion);

        foreach ($lineasLiquidacion as $ll) {
            fwrite($archivoMouraLiquidacion, $ll['linea0'] . PHP_EOL);
            fwrite($archivoMouraLiquidacion, $ll['linea1'] . PHP_EOL);
            fwrite($archivoMouraLiquidacion, $ll['linea2'] . PHP_EOL);
            fwrite($archivoMouraLiquidacion, PHP_EOL);
        }

        fclose($archivoMouraLiquidacion);
    }

    private function createXLSX()
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $encabezado = [
            "Data Emissão",
            "Tp.doc.",
            "Empresa",
            "Data Lançamento",
            "Período",
            "Moeda/taxa câm.",
            "Grp. ledger",
            "Referência",
            "Txt.cab.doc.",
            "ChvLnçt",
            "Conta",
            "Cód.RzE",
            "Montante",
            "Forma de Pagamento",
            "Bloqueio de Pagamento",
            "Condição de Pagamento",
            "Data Base",
            "Atribuição",
            "Texto",
            "Centro de Custo",
            "Ordem",
            "Elemento PEP",
            "Diagrama de Rede",
            "Item do Diagrama",
            "Centro de lucro",
            "Divisão",
            "Local de Negócios",
            "Cod Imposto"
        ];
        $col = 'A';
        foreach ($encabezado as $titulo) {
            $sheet->setCellValue($col . '1', $titulo);
            $col++;
        }
        $archivoMouraLiquidacion = fopen('./archivosFixed/LiquidacionesBancos' . DIVISION_BSAS . $this->fechaurl . '.txt', 'r');
        $fila = 2;
        while (($linea = fgets($archivoMouraLiquidacion)) !== false) {
            $campos = str_split($linea, 32);
            $columna = 'A';
            foreach ($campos as $valor) {
                $sheet->setCellValue($columna . $fila, trim($valor));
                $columna++;
            }
            $fila++;
        }
        fclose($archivoMouraLiquidacion);
        $writer = new Xlsx($spreadsheet);
        $writer->save('./archivosFixed/LiquidacionesBancos' . DIVISION_BSAS . $this->fechaurl . '.xlsx');
    }

    private function obtenerLineasLiquidacion($fechaLiquidacion)
    {
        $query = "SELECT linea0, linea1, linea2
			FROM liquidacionesArchivoPatch
			WHERE fecha  = ?";

        try {
            $query = $this->db->prepare($query);
            $query->execute(array($fechaLiquidacion));
            $result = $query->fetchAll(\PDO::FETCH_ASSOC);
            return $result;
        } catch (\PDOException $e) {
            exit($e->getMessage());
        }
    }

    private function ensureTableExists()
    {
        $query = "CREATE TABLE IF NOT EXISTS `liquidacionesArchivoPatch` (
                    `linea0` VARCHAR(1000) NOT NULL COLLATE 'utf8mb4_unicode_ci',
                    `linea1` VARCHAR(1000) NOT NULL COLLATE 'utf8mb4_unicode_ci',
                    `linea2` VARCHAR(1000) NOT NULL COLLATE 'utf8mb4_unicode_ci',
                    `fecha` DATETIME NOT NULL
                );";
        $this->db->getConnection()->exec($query);
    }
}

$archiveGenerator = new ArchiveGenerator($argv[1]);
