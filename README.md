phpbmpread
==========

PHP library to read Windows BMP files

This library will convert windows BMP files to img object to use with the PHP GD library. This works only with Windows BMP files of color depth 4, 8, and 24.

This is based on spec: http://en.wikipedia.org/wiki/BMP_file_format

resource function imagecreatefrombmp( resource $filename | $string $bmpraw )

resource function printbmpinfo( resource $filename | $string $bmpraw )

sample (bmp to png) as img
==========================

<?php

require 'BMP.php'; //BMP support

try {

	$im = imagecreatefrombmp('ms4bit.bmp');
	
}

catch(\Exception $e) {

	echo($e);
	exit;

}

	//output the image
	header('Content-type: image/png');
	imagepng($im);
	imagedestroy($im);
	exit;

?>
