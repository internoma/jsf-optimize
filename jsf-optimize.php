#!/usr/bin/php
<?php
/**
 * jsf-optimize.
 * @authors Alfredo Gonzalez P. <alfredo@internoma.com>
 * @date    2015-01-16 20:55:33
 * @version 1.0.1
 */

error_reporting(E_ERROR);

if ( php_sapi_name() !== 'cli' ) exit('Solo para linea de comandos');

/**
 * Definición de colores de consola CLI
 * */

$cW = "\033[37m"; // White
$cG = "\033[32m"; // Green
$cR = "\033[31m"; // Red
$cY = "\033[33m"; // Yellow
$cB = "\033[36m"; // Blue
$cO = "\033[0m";  // Default

/**
 * Definición de salida de créditos y ayuda para consola CLI
 * */

if ($argc == 1 || in_array($argv[1], array('--help', '-help', '-h', '-?'))) {
$output = <<< EOT

{$cB}2015 @by Alfredo González P. - v.1.0.0beta{$cO}

{$cW}Utilidad para procesar archivos JSF y aplicarles los xmlns correctos.

	Uso:\n
	{$cG}php {$argv[0]} <path> [opcional:tipo]{$cO}

	{$cW}<path> carpeta/directorio de procesamiento.

	<tipo> html / ui:composition (por defecto).

	Con la opción --help, -help -h or -?, se obtiene esta información.{$cO}\n\n
EOT;
die($output);
}

/**
 * Definición de salida de confirmación de proceso para consola CLI
 * */

$argDir  = isset($argv[1]) ? $argv[1] : '.';
$argType = isset($argv[2]) ? $argv[2] : 'ui:composition';

echo "{$cB}=========================================={$cO}\n";
echo "{$cB}Está a punto de procesar los archivos JSF.{$cO}\n";
echo "{$cB}=========================================={$cO}\n";
echo "Continuar con el proceso? ({$cG}S{$cO}/{$cW}N{$cO}) - ";

$stdin = fopen('php://stdin', 'r');
$response = fgetc($stdin);
if (strtoupper($response) != 'S') {
   echo "Proceso abortado por el usuario.\n";
   exit;
}

/**
 * Inicio del procesamiento
 * */

$time_start = microtime(true);

echo "\nProcesando... \n\n";

/**
 * Definición de valores de xmlns
 * */

$xmlns = array(
	'xml'       => '<?xml version="1.0" encoding="UTF-8"?>',
	'dt'        => '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">',
	'template'  => 'template        = "/PATH/FOR/YOUR/TEMPLATES/template.xhtml"',

	'ini'       => 'xmlns           = "http://www.w3.org/1999/xhtml"',
	'composite' => 'xmlns:composite = "http://java.sun.com/jsf/composite"',
	'ui'        => 'xmlns:ui        = "http://java.sun.com/jsf/facelets"',
	'f'         => 'xmlns:f         = "http://java.sun.com/jsf/core"',
	'h'         => 'xmlns:h         = "http://java.sun.com/jsf/html"',
	'p'         => 'xmlns:p         = "http://primefaces.prime.com.tr/ui"',
	'c'         => 'xmlns:c         = "http://java.sun.com/jsp/jstl/core"',
	'fn'        => 'xmlns:fn        = "http://java.sun.com/jsp/jstl/functions"',
	'cc'        => 'xmlns:cc        = "http://java.sun.com/jsf/composite/componentes"',
);

/**
 * Función getXhtmlFile()
 * @return array
 * */

function getXhtmlFile($file) {
	try {
		$arrayOut = [];
		$fd = fopen ($file, "r");
		if (!$fd) throw new Exception('ha sido imposible leer el archivo: ' . $file);
		while (!feof ($fd)) { 
			$buffer = fgets($fd, 4096);
			if (preg_match('/<!DOCTYPE((.|\n|\r)*?)\">/ui', $buffer)) continue;
			if (preg_match("/<\?xml(.+)\?>/ui", $buffer)) continue;
			if (preg_match("/<\/ui:composition>/ui", $buffer)) continue;
			if (preg_match("/<\/html>/ui", $buffer)) continue;
			if (trim($buffer," \t\n\r\0\x0B") !== '') $arrayOut[] = $buffer; 
		} 
		fclose ($fd);
		return $arrayOut;
	}
	catch (Exception $e) {
		printf("{$cR}Ha ocurrido un error: {$cY}([%s]){$cR} que no permite el procesamiento.{$cO}\n\n", $e->getMessage());
		exit;
	}
}

/**
 * Función setHeader()
 * @return string
 * */

function setHeader($file, $type='ui:composition') {
	global $xmlns;
	$tags  = [];
	foreach($file as $key => $line) {
		if (preg_match('/<(\w+):[^>]*>/i', $line, $matches)) {
			$tags[] .= $matches[1];
		}
	}
	$tags = array_unique($tags);
	if ($type == 'ui:composition') {
		$out  = $xmlns['xml'] . PHP_EOL;
		$out .= '<ui:composition ';
	}
	if ($type == 'html') {
		$out  = $xmlns['xml'] . PHP_EOL . $xmlns['dt'] . PHP_EOL;
		$out .= '<html ';
	}
		$out .= PHP_EOL . "\t" . $xmlns['ini'];
	foreach ($tags as $value) {
		$out .= PHP_EOL . "\t" . $xmlns[$value];
	}
	$out .= ' >' . PHP_EOL;
	return $out;
}


/**
 * Ejecución recursiva del proceso
 * */

$dir_iterator = new RecursiveDirectoryIterator($argDir);
$iterator     = new RecursiveIteratorIterator($dir_iterator, RecursiveIteratorIterator::SELF_FIRST);
$contador     = 0;

try {
	foreach ($iterator as $file) {
		$filename = (string)$file;
		if (preg_match('/(.+).xhtml/', $filename)){
			$thisFile    = getXhtmlFile($filename);
			$thisStrFile = implode($thisFile);
			$thisStrFile = preg_replace("/<ui:composition((.|\n|\r+)[^>]*>\n)/ui", "", $thisStrFile);
			$thisStrFile = preg_replace("/<html((.|\n|\r+)[^>]*>\n)/ui", "", $thisStrFile);
			$dataFile    = setHeader($thisFile, $argType) . $thisStrFile . "\n</". $argType .'>';
			$writeOK     = file_put_contents($filename, $dataFile);
			if (!$writeOK) throw new Exception('ha sido imposible escribir el archivo: ' . $filename);
			echo "{$cR}\"{$filename}\"{$cW} --> procesado" . PHP_EOL;
			$contador++;
			flush();
		}
	}
}
catch (Exception $e) {
	printf("{$cR}Ha ocurrido un error: {$cY}([%s]){$cR} que no permite el procesamiento.{$cO}\n\n", $e->getMessage());
	exit;
}

/**
 * Definición de salida de conclusión de proceso para consola CLI
 * */

echo PHP_EOL . "{$cG}Proceso concluido satisfactoriamente..." . PHP_EOL;

$time = microtime(true) - $time_start;

echo "{$cY}Tiempo de ejecucion para {$contador} archivos: {$time} segundos{$cO}";

?>




