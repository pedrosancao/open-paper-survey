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
		array_shift($widths); // discart first long space
		$elements = count($widths);
		if ($elements < 17) { // min length I2/5
			return false;
		}

		sort($widths);
		$bar = $space = array();
		foreach ($widths as $key => $value) {
			if ($key % 2) {
				array_push($space, $value);
			} else {
				array_push($bar, $value);
			}
		}
		$bars = count($bar);
		$spaces = count($space);
		return array(
			'nb' => $bar[round($bars * 0.3)],
			'wb' => $bar[min(round($bars * 0.8), $bars - 1)],
			'ns' => $space[round($spaces * 0.3)],
			'ws' => $space[min(round($spaces * 0.8), $spaces - 1)],
		);
	}

	/**
	 * Get the narrow/wide representation as string
	 * 
	 * @param array $widths An array of bars widths
	 * @param int $narrowBar An estimate width of narrow bars
	 * @param int $wideBar An estimate width of wide bars
	 * @param int $narrowSpace An estimate width of narrow spaces
	 * @param int $wideSpace An estimate width of wide spaces
	 * @return string narrow/wide representation
	 */
	private static function widthsToNarrowWide(array $widths, $narrowBar, $wideBar, $narrowSpace, $wideSpace) {
		$string = '';
		$keys = array('b', 's');
		$narrow = array_combine($keys, array($narrowBar, $narrowSpace));
		$wide = array_combine($keys, array($wideBar, $wideSpace));
		$distance = $nmin = $nmax = $wmin = $wmax = array();
		foreach ($keys as $key) {
			$distance[$key] = ($wide[$key] - $narrow[$key] - 1);
			$nmin[$key] = max($narrow[$key] - ceil($distance[$key] * 0.55), 1);
			$nmax[$key] = $narrow[$key] + floor($distance[$key] * 0.45);
			$wmin[$key] = $wide[$key] - ceil($distance[$key] * 0.55);
			$wmax[$key] = $wide[$key] + ceil($distance[$key] * 0.5);
		}

		foreach($widths as $i => $width) {
			$key = $keys[1 - $i % 2];
			if ($nmin[$key] <= $width && $width <= $nmax[$key]) {
				$string .= "N";
			}
			elseif ($wmin[$key] <= $width && $width <= $wmax[$key]) {
				$string .= "W";
			}
			else {
				$string .= "J"; // J for junk
			}
		}

		// remove junk bits from start and end of string
		if (strpos($string, 'J') !== false) {
			$string = trim($string, 'J');
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
	private static function getCodeI25($bars, $widths) {
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
			$black = $white = '';
			$blackWidths = $whiteWidths = array();
			for ($j = 0; $j < 10; $j++) {
				if ($j & 0b1) { // odd $j
					$white .= $bars[$i + $j];
					array_push($whiteWidths, $widths[$i + $j + 1]);
				} else { // even $j
					$black .= $bars[$i + $j];
					array_push($blackWidths, $widths[$i + $j + 1]);
				}
			}
			$success = true;
			if (!isset($conversionTable[$black]) && !self::errorCorrectionI25($blackWidths, $black)) {
				$success = false;
			} elseif (!isset($conversionTable[$white]) && !self::errorCorrectionI25($whiteWidths, $white)) {
				$success = false;
			}
			if (!$success) {
				return 'false';
			}
			$code .= $conversionTable[$black] . $conversionTable[$white];
		}

		return $code;
	}

	/**
	 * Tries to find a valid Interlaced 2 of 5 digit on the supplied widths array
	 * if the diference between the 4th and 3th greater is more than 1
	 * 
	 * @param array $widths array of widths (length must be 5)
	 * @param string $bars the original narrow/wide digit to be overwrite
	 * @return boolean error successfully corrected
	 */
	private static function errorCorrectionI25($widths, &$bars) {
		$widthsCopy = $widths;
		sort($widthsCopy);
		if ($widthsCopy[3] - $widthsCopy[2] > 1) {
			$bars = '';
			foreach ($widths as $width) {
				if ($width >= $widthsCopy[3]) {
					$bars .= 'W';
				} elseif ($width <= $widthsCopy[2]) {
					$bars .= 'N';
				}
			}
			return true;
		}
		return false;
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
	public static function read($image, $step = 1, $length = BARCODE_LENGTH_PID) {
		$targetWidth = 1240;
		$width = imagesx($image);
		$height = imagesy($image);
		if ($width < $targetWidth * 0.7) {
			$targetHeight = round($height * ($targetWidth / $width));
			$newImage = imagecreatetruecolor($targetWidth, $targetHeight);
			imagecopyresampled($newImage, $image, 0, 0, 0, 0, $targetWidth, $targetHeight, $width, $height);
			imagedestroy($image);
			$image = $newImage;
		}
		if (function_exists('imagefilter') &&
			function_exists('imagetruecolortopalette') &&
			function_exists('imagecolorset') &&
			function_exists('imagecolorclosest'))
		{
			// add contrast to reduce noise
			imagefilter($image, IMG_FILTER_CONTRAST, 30);
			// Gaussian blur to fill in holes from dithering
			imagefilter($image, IMG_FILTER_GAUSSIAN_BLUR);
			// force two colors; dithering = FALSE
			imagetruecolortopalette($image, FALSE, 2);
			// find the closest color to black and replace it with actual black
			imagecolorset($image, imagecolorclosest($image, 0, 0, 0), 0, 0, 0);
			// find the closest color to white and replace it with actual white
			imagecolorset($image, imagecolorclosest($image, 255, 255, 255), 255, 255, 255);
		}

		for ($y = $step; $y < $height - $step; $y += $step) {
			$widths = self::getBarWidths($image, $y);
			$barWidth = self::getNarrowWideWidth($widths);
			if (!empty($barWidth)) {
				$bars = self::widthsToNarrowWide($widths, $barWidth['nb'], $barWidth['wb'], $barWidth['ns'], $barWidth['ws']);
				if(self::validateI25($bars)) {
					$code = self::getCodeI25($bars, $widths);
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

// theses functions were keep for backward compatibility,
// while the other modules are not updates

function barcode($image, $step = 1, $length = false) {
	$params = func_get_args();
	return call_user_func_array('Barcode::read', $params);
}
