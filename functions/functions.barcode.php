<?php

/*	Copyright Deakin University 2007,2008
 *	Written by Adam Zammit - adam.zammit@deakin.edu.au
 *	For the Deakin Computer Assisted Research Facility: http://www.deakin.edu.au/dcarf/
 *	
 *	This file is part of queXF
 *	
 *	queXF is free software; you can redistribute it and/or modify
 *	it under the terms of the GNU General Public License as published by
 *	the Free Software Foundation; either version 2 of the License, or
 *	(at your option) any later version.
 *	
 *	queXF is distributed in the hope that it will be useful,
 *	but WITHOUT ANY WARRANTY; without even the implied warranty of
 *	MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *	GNU General Public License for more details.
 *	
 *	You should have received a copy of the GNU General Public License
 *	along with queXF; if not, write to the Free Software
 *	Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
 *
 */

include_once(dirname(__FILE__).'/../config.inc.php');

/**
 * Barcode functions
 *
 * @author Pedro Sanção <pedro@sancao.com.br>
 */
class Barcode {

	/**
	 * Find the bars widths
	 *
	 * @param resource $image A 1 bit image (of type GD) containing a barcode
	 * @param int $y Y coordinate to read
	 * @return array An array of bar widths
	 */
	private static function getBarWidths($image, $y) {
		$width = imagesx($image);
		$widths = array();
		$count = 0;
		$color = imagecolorat($image, 0, $y);
		for ($x = 0; $x < $width; $x++) {
			$rgb = imagecolorat($image, $x, $y);
			if ($rgb != $color) {
				$widths[] = $count;
				$count = 0;
				$color = $rgb;
			}
			$count++;	
		}
		return $widths;
	}

	/**
	 * Guess the width of narrow and wide bars
	 *
	 * @param array $widths An array of bars widths
	 * @return array An array containg the width of narrow and wide bars
	 */
	private static function getNarrowWideWidth(array $widths) {
		$elements = count($widths);
		if ($elements < 4) {
			return array(
				'n' => 0,
				'w' => 0,
			);
		}

		sort($widths);
		return array(
			'n' => $widths[round($elements / 4) + 1],
			'w' => $widths[$elements - round($elements / 4) + 1],
		);
	}

	/**
	 * Get the narrow/wide representation as string
	 * 
	 * @param array $widths An array of bars widths
	 * @param int $narrow An estimate width of narrow bars
	 * @param int $wide An estimate width of wide bars
	 * @return string narrow/wide representation
	 */
	private static function widthsToNarrowWide(array $widths, $narrow, $wide) {
		//give a large tolerance

		$tolerance = (($wide - $narrow) - 1) / 2;
		$string = '';

		$nmin = ($narrow - $tolerance);
		if ($nmin <= 0) {
			$nmin = 1;
		}

		foreach($widths as $width) {
			if (($width >= ($nmin)) && ($width <= ($narrow + $tolerance))) {
				$string .= "N";
			}
			elseif (($width >= ($wide - $tolerance)) && ($width <= ($wide + $tolerance))) {
				$string .= "W";
			}
			else {
				$string .= "J"; //J for junk
			}
		}

		//remove junk bits from start and end of string
		$firstJ = strpos($string, 'J');
		if ($firstJ <= ((strlen($string) / 4))) {
			$string = substr($string, $firstJ + 1);
		}

		$lastJ = strpos($string, 'J', ((strlen($string) / 4) * 3));
		if ($lastJ >= ((strlen($string) / 4) * 3)) {
			$string = substr($string, 0, $lastJ);
		}

		return $string;
	}

	/**
	 * Validate an interleaved 2 of 5 barcode
	 * 
	 * @param string $bars narrow/wide representation of barcode
	 * @return boolean
	 */
	private static function validateI25($bars) {
		// length must be 10 * length + 7
		if (fmod(strlen($bars), 10) != 7) {
			return false;
		}

		// must start with nnnn
		if (strncmp($bars, 'NNNN', 4) !== 0) {
			return false;
		}

		// must end with wnn
		if (substr($bars, -3) !== 'WNN') {
			return false;
		}

		return true;
	}

	/**
	 * Get a interleaved 2 of 5 code
	 * 
	 * @param string $bars narrow/wide representation of barcode
	 * @return string the value of code of 'false' on failure
	 */
	private static function getCodeI25($bars) {
		$conversionTable = array(
			'NNWWN' => 0,
			'WNNNW' => 1,
			'NWNNW' => 2,
			'WWNNN' => 3,
			'NNWNW' => 4,
			'WNWNN' => 5,
			'NWWNN' => 6,
			'NNNWW' => 7,
			'WNNWN' => 8,
			'NWNWN' => 9,
		);

		$code = '';
		// ignore the first 4 and last 3
		for ($i = 4; $i < strlen($bars) - 3; $i += 10) {
			$b1 = $bars[$i] . $bars[$i + 2] . $bars[$i + 4] . $bars[$i + 6] . $bars[$i + 8];
			$b2 = $bars[$i + 1] . $bars[$i + 3] . $bars[$i + 5] . $bars[$i + 7] . $bars[$i + 9];
			if (!isset($conversionTable[$b1]) || !isset($conversionTable[$b2])) {
				return 'false';
			}
			$code .= $conversionTable[$b1] . $conversionTable[$b2];
		}

		return $code;
	}

	/**
	 * Validate a codabar barcode
	 * 
	 * @param string $bars narrow/wide representation of barcode
	 * @return boolean
	 */
	private static function validateCodabar($bars) {
		// length + 1 must be a multiple of 8 (each character is 7 bars/spaces and a space)
		if (fmod(strlen($bars) + 1, 8) != 0) {
			return false;
		}

		// must start and end with a stop character (A, B, C or D)
		$stopChars = range('A', 'D');
		$start = self::getCodabar(substr($bars, 0, 8));
		if (!in_array($start, $stopChars)) {
			return false;
		}
		$end = self::getCodabar(substr($bars, -7) . 'N');
		if (!in_array($end, $stopChars)) {
			return false;
		}
		
		return true;
	}

	/**
	 * Get a codabar code
	 *
	 * @see http://www.barcodesymbols.com/codabar.htm
	 *
	 * @param string $bars narrow/wide representation of barcode
	 * @return string the value of code of 'false' on failure
	 */
	private static function getCodabar($bars) {
		$conversionTable = array(
			'NNNNNWWN' => 0,
			'NNNNWWNN' => 1,
			'NNNWNNWN' => 2,
			'WWNNNNNN' => 3,
			'NNWNNWNN' => 4,
			'WNNNNWNN' => 5,
			'NWNNNNWN' => 6,
			'NWNNWNNN' => 7,
			'NWWNNNNN' => 8,
			'WNNWNNNN' => 9,
			'NNNWWNNN' => '-',
			'NNWWNNNN' => '$',
			'WNNNWNWN' => ':',
			'WNWNNNWN' => '/',
			'WNWNWNNN' => '.',
			'NNWNWNWN' => '+',
			'NNWWNWNN' => 'A',
			'NWNWNNWN' => 'B',
			'NNNWNWWN' => 'C',
			'NNNWWWNN' => 'D',
		);

		$code = '';
		for ($i = 0; $i < strlen($bars); $i+= 8) {
			$bar = substr($bars, $i, 8);
			if (!isset($conversionTable[$bar])) {
				return 'false';
			}
			$code .= $conversionTable[$bar];
		}

		return $code;
	}

	/**
	 * Search and read an interleaved 2 of 5 or a codabar barcode
	 * 
	 * @param resource $image image of type GD
	 * @param int $step pixels to read
	 * @param int $length length of barcode
	 * @return string the found barcode or false
	 */
	public static function read($image, $step = 1, $length = BARCODE_LENGTH) {
		if (function_exists('imagefilter') &&
			function_exists('imagetruecolortopalette') &&
			function_exists('imagecolorset') &&
			function_exists('imagecolorclosest'))
		{
			// Gaussian blur to fill in holes from dithering
			imagefilter($image, IMG_FILTER_GAUSSIAN_BLUR);
			// force two colors; dithering = FALSE
			imagetruecolortopalette($image, FALSE, 2);
			// find the closest color to black and replace it with actual black
			imagecolorset($image, imagecolorclosest($image, 0, 0, 0), 0, 0, 0);
			// find the closest color to white and replace it with actual white
			imagecolorset($image, imagecolorclosest($image, 255, 255, 255), 255, 255, 255);
		}

		$height = imagesy($image);

		for ($y = $step; $y < $height - $step; $y += $step) {
			$widths = self::getBarWidths($image, $y);
			$barWidth = self::getNarrowWideWidth($widths);
			if ($barWidth['n'] != 0 && $barWidth['w'] != 0) {
				$bars = self::widthsToNarrowWide($widths, $barWidth['n'], $barWidth['w']);
				if(self::validateI25($bars, $image, $y)) {
					$code = self::getCodeI25($bars);
					if ($code != "false" && (!$length || strlen($code) == $length)) {
						return $code;
					}
				}
				elseif (self::validateCodabar($bars)) {
					$code = NWtoCodeCodaBar($bars . 'N'); // add the last space
					if ($code != "false") {
						$code = substr($code, 1, -1); // remove the start and stop characters
						if (!$length || strlen($code) == $length) {
							return $code;
						}
					}
				}
			}
		}
		return false;
	}

}

/**
 * Search and read an interleaved 2 of 5 or a codabar barcode
 *
 * @param resource $image image of type GD
 * @param int $step pixels to read
 * @param int $length length of barcode
 * @return string the found barcode or false
 */
function barcode($image, $step = 1, $length = false) {
	$params = func_get_args();
	call_user_func_array('Barcode::read', $params);
}
