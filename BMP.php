<?php

/*

Read Windows BMP v.1.1
Licence: MIT
Author: Nicholas Marus (nmarus@gmail.com) 

>>Description<< 

	This will convert windows BMP files to img object to use with the PHP GD 
	library. This works only with Windows BMP files of color depth 4, 8, and 24.

	This is based on spec: http://en.wikipedia.org/wiki/BMP_file_format

>>Main Functions<<

	function imagecreatefrombmp( resource $filename | $string $bmpraw )

	-This function opens a bmp file or raw data and returns an image object. 

	function printbmpinfo( resource $filename | $string $bmpraw )

	-This function returns an html table of all the header values of the BMP file.

*/

//main function
function imagecreatefrombmp($bmp) {

	$bmpraw = getbmpraw($bmp);

	try {

		$im = imgbmp($bmpraw);
		return $im;

	}

	catch(\Exception $e) {

		throw($e);
		return false;

	}
	
}

//print info regarding bmp
function printbmpinfo($bmp) {

	$bmpraw = getbmpraw($bmp);

	try {

		$header = getbmpheader($bmpraw);

	}

	catch(\Exception $e) {

		echo($e);
		return false;

	}

	echo('<table border=1>');
	foreach ($header as $key => $value) {
		echo('<tr><td>'.$key.':</td><td>'.$value.'</td></tr>');
	}
	echo('</table>');

	return true;

}

//checks if $bmp is raw or file and returns raw bmp data
function getbmpraw($bmp) {

	if(is_string($bmp)) {

		if($f = fopen($bmp, "rb")) {

			$bmpraw = fread($f, filesize($bmp));
			fclose($f);
			return $bmpraw;

		} else {
			
			$error = 'Error opening file '.$bmp;
			throw new Exception($error);
			return false;

		}
	
	} else if(is_object($bmp)) {

		return $bmp;

	} else {

		$error = 'Invalid variable passed to function';
		throw new Exception($error);
		return false;

	}

}

//decodes $bmpraw and returns image object
function imgbmp($bmpraw) {
    
    try {

		$header = getbmpheader($bmpraw);

	}

	catch(\Exception $e) {

		throw($e);
		return false;
		
	}

    //set info from header about BMP
    $w  = $header['width']; 		//image width
	$h  = $header['height']; 		//image height
	$s  = $header['bitmap_start']; 	//offset where BMP data
	$b  = $header['bits_pixel']; 	//bits per pixel
	$ds = $header['dib_size']; 		//size of dib header
	$hs = 14; 						//size of BMP file header data
	$ps = pow(2,$b) * 4; 			//size of palette
	$bp = array('4','8','24');		//array of supported bp

	//validate $b matches what this script can read
	if(!in_array($b, $bp)) {
		$error = 'BMP bits per pixel not supported';
 		throw new Exception($error);
 		return false;
	}

	//check for and grab color palette on 4,8 bit images
	if(($b <= 8) && $s >= $hs+$ds+$ps) { 
		$cp = substr($bmpraw, $hs + $ds, $ps); //grab color palette after header
		$cp = bin2hex($cp); //convert to hex
		$cp = str_split($cp, 8); //split into color codes
	}	

	//create image object
	$img = imagecreatetruecolor($w, $h);

	//trim header from BMP
	$bmpraw = substr($bmpraw, $s);

	//convert to string of HEX
    $bmpraw = bin2hex($bmpraw);

    //get row size with padding (must be multiple of 4 bytes)
    $row_size = ceil(($b * $w / 8) / 4) * 8;

    //split data to array of rows
	$bmpraw=str_split($bmpraw,$row_size);

	//process data
    for($y=0; $y<$h; $y++) {
		
    	//get 1 row (flip row vertical order)
		$row = $bmpraw[abs(($h-1)-$y)];

		//get row pixel data (remove trailing buffer)
		$row = substr($row, 0, $w * $b / 4);

		//split row to pixel
		$pixels = str_split($row, $b / 4);

		//process 24bit bitmap
    	if($b == 24) {
		  	//write pixel data for row to img
	    	for($x=0; $x<$w; $x++) {
	    		imagesetpixel($img, $x, $y, getimgcolorfrom24($pixels[$x]));
	    	}
	    //process palette based bitmap
    	} else if(in_array($b, $bp)) { 
    		//write pixel data for row to img
	    	for($x=0; $x<$w; $x++) {
	    		imagesetpixel($img, $x, $y, getimgcolorfrompalette($pixels[$x],$b,$cp));
	    	}

    	} else {

    		return false;
    
    	}

	}

	unset($bmpraw);
	return $img;
    
}

//returns header of BMP raw
function getbmpheader($bmpraw) {

	//grab first 54 to determine file info
    if($header = substr($bmpraw, 0, 54)) {

	    $header = unpack(	'a2type/'. 			//00-2b - header to identify file type
	    					'Vfile_size/'.		//02-4b - size of bmp in bytes
	    					'vreserved1/'.		//06-2b - reserved
	    					'vreserved2/'.		//08-2b - reserved
	    					'Vbitmap_start/'.	//10-4b - offset of where bmp pixel array can be found
	    					'Vdib_size/' .		//14-4b - size of dib header
							'Vwidth/'.			//18-4b - width on pixels
							'Vheight/'.			//22-4b - height in pixels
							'vcolor_planes/'.	//26-2b - number of color planes
							'vbits_pixel/'. 	//28-2b - bits per pixel
							'Vcompression/'.	//30-4b - compression method
							'Vimage_size/'.		//34-4b - image size in bytes
							'Vh_resolution/'.	//38-4b - horizontal resolution
							'Vv_resolution/'.	//42-4b - vertical resolution
							'Vcolor_palette/'.	//46-4b - number of colors in palette
							'Vimp_colors/'		//50-4b - important colors
		, $header);

		return $header;

	} else {

		$error = 'BMP header data not found';
		throw new Exception($error);
		return false;

	}

	//validate bitmap
    if($header['type'] != 'BM') {
 		$error = 'BMP not valid';
 		throw new Exception($error);
 		return false;
    }

}

//24 bit to image color function
function getimgcolorfrom24($hc) {

	$hc = str_split($hc,2);

	$r = hexdec($hc[2]);
	$g = hexdec($hc[1]);
	$b = hexdec($hc[0]);

	return ($r * 65536) + ($g * 256) + $b;

}

//4,8 bit to image color function
function getimgcolorfrompalette($hc,$b,$cp) {

	$r = 0; //red
	$g = 0; //green
	$b = 0; //blue

	if($cp != 0) { //if defined, set rgb value based on palette

		$r = hexdec(substr($cp[hexdec($hc)],4,2));
		$g = hexdec(substr($cp[hexdec($hc)],2,2));
		$b = hexdec(substr($cp[hexdec($hc)],0,2));

		return ($r * 65536) + ($g * 256) + $b;

	} else if($b == '4') { //else if no palette and 4 bit, use standard 16 color palette as defined below

		switch ($hc) {
			
			case '0': //black
			$r = 0; $g = 0; $b = 0;
			break;

			case '1': //dark red
			$r = 128; $g = 0; $b = 0;
			break;

			case '2': //red
			$r = 255; $g = 0; $b = 0;
			break;

			case '3': //pink
			$r = 255; $g = 0; $b = 255;
			break;

			case '4': //teal
			$r = 0; $g = 128; $b = 128;
			break;

			case '5': //green
			$r = 0; $g = 128; $b = 0;
			break;

			case '6': //bright green
			$r = 0; $g = 255; $b = 0;
			break;

			case '7': //turquoise
			$r = 0; $g = 255; $b = 255;
			break;

			case '8': //dark blue
			$r = 0; $g = 0; $b = 128;
			break;

			case '9': //violet
			$r = 128; $g = 0; $b = 128;
			break;

			case 'a': //blue
			case 'A':
			$r = 0; $g = 0; $b = 255;
			break;

			case 'b': //gray 25%
			case 'B':
			$r = 192; $g = 192; $b = 192;
			break;

			case 'c': //gray 50%
			case 'C':
			$r = 128; $g = 128; $b = 128;
			break;

			case 'd': //dark yellow
			case 'D':
			$r = 128; $g = 128; $b = 0;
			break;

			case 'e': //yellow
			case 'E':
			$r = 255; $g = 255; $b = 0;
			break;

			case 'f': //white
			case 'F':
			$r = 255; $g = 255; $b = 255;
			break;

			default:
			$r = 0; $g = 0; $b = 0;
			break;
		}

		return ($r * 65536) + ($g * 256) + $b;

	} else {

		$error = 'BMP palette not found and image is not 4 bpp';
 		throw new Exception($error);
 		return false;

	}
}

?>
