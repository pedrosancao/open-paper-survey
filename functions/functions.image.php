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

include_once(dirname(__FILE__) . '/../config.inc.php');

/**
 * Image functions
 *
 * @author Pedro Sanção <pedro@sancao.com.br>
 */
class Image {

	/**
	 * Integer division
	 * 
	 * @param $x 
	 * @param $y
	 * @return the result of x/y or FALSE if div by 0
	 *
	 * sourced from: http://us.php.net/manual/en/language.operators.arithmetic.php#76887
	 */
	private static function divideInt($x, $y) {
		if ($x == 0) {
			return 0;
		}
		if ($y == 0) {
			return false;
		}
		return (int) floor($x / $y);
	}

	/**
	 * Transform keys of an array of coordinates from strings to corresponding numeric
	 * 
	 * @param array $coordinates An array of coordinates with string keys
	 * @return array An array of coordinates with numeric keys
	 */
	private static function keyToIndex($coordinates) {
		return array(
			0 => $coordinates['tlx'],
			1 => $coordinates['tly'],
			2 => $coordinates['trx'],
			3 => $coordinates['try'],
			4 => $coordinates['blx'],
			5 => $coordinates['bly'],
			6 => $coordinates['brx'],
			7 => $coordinates['bry'],
		);
	}

	/**
	 * Transform keys of an array of coordinates from numeric to corresponding strings
	 * 
	 * @param array $coordinates An array of coordinates with numeric keys
	 * @return array An array of coordinates with string keys
	 */
	private static function indexToKey($coordinates) {
		return array(
			'tlx' => $coordinates[0],
			'tly' => $coordinates[1],
			'trx' => $coordinates[2],
			'try' => $coordinates[3],
			'blx' => $coordinates[4],
			'bly' => $coordinates[5],
			'brx' => $coordinates[6],
			'bry' => $coordinates[7],
		);
	}

	/**
	 * Sanitize the coordinates of a point so it fits within the given size
	 * 
	 * @param array $point An array containing X and Y coordinates
	 * @param int $width max width
	 * @param int $height mas height
	 * @return array Sanitized point coordinates
	 */
	private static function sanitizePoint($point, $width, $height) {
		return array(
			round(min($width, max(0, $point[0]))),
			round(min($height, max(0, $point[1]))),
		);
	}

	/**
	 * Use the presence of corner lines to see if the page is blank or not
	 * 
	 * @param resource $image image of type GD
	 * @param array $page template page data
	 * @return boolean
	 */
	public static function isPageBlank($image, $page) {
		$corners = array(
			self::lineVertical($page['TL_VERT_TLX'], $page['TL_VERT_TLY'], $page['TL_VERT_BRX'], $page['TL_VERT_BRY'], $image, $page['VERT_WIDTH']),
			self::lineHorizontal($page['TL_HORI_TLX'], $page['TL_HORI_TLY'], $page['TL_HORI_BRX'], $page['TL_HORI_BRY'], $image, $page['HORI_WIDTH']),
			self::lineVertical($page['TR_VERT_TLX'], $page['TR_VERT_TLY'], $page['TR_VERT_BRX'], $page['TR_VERT_BRY'], $image, $page['VERT_WIDTH'], 'rtl'),
			self::lineHorizontal($page['TR_HORI_TLX'], $page['TR_HORI_TLY'], $page['TR_HORI_BRX'], $page['TR_HORI_BRY'], $image, $page['HORI_WIDTH']),
			self::lineVertical($page['BL_VERT_TLX'], $page['BL_VERT_TLY'], $page['BL_VERT_BRX'], $page['BL_VERT_BRY'], $image, $page['VERT_WIDTH']),
			self::lineHorizontal($page['BL_HORI_TLX'], $page['BL_HORI_TLY'], $page['BL_HORI_BRX'], $page['BL_HORI_BRY'], $image, $page['HORI_WIDTH'], 'btt'),
			self::lineVertical($page['BR_VERT_TLX'], $page['BR_VERT_TLY'], $page['BR_VERT_BRX'], $page['BR_VERT_BRY'], $image, $page['VERT_WIDTH'], 'rtl'),
			self::lineHorizontal($page['BR_HORI_TLX'], $page['BR_HORI_TLY'], $page['BR_HORI_BRX'], $page['BR_HORI_BRY'], $image, $page['HORI_WIDTH'], 'btt'),
		);

		return in_array(false, $corners, true);
	}

	/**
	 * Search the corner lines given an image and the detection areas coordinates
	 * 
	 * @param resource $image image of type GD
	 * @param array $coordinates An array containing the 4 corner lines detection areas coordinates
	 * @return array An array containing the 4 corner lines coordinates
	 */
	public static function getCorners($image, $coordinates) {
		return array(
			self::lineVertical($coordinates['TL_VERT_TLX'], $coordinates['TL_VERT_TLY'], $coordinates['TL_VERT_BRX'], $coordinates['TL_VERT_BRY'], $image, $coordinates['VERT_WIDTH']),
			self::lineHorizontal($coordinates['TL_HORI_TLX'], $coordinates['TL_HORI_TLY'], $coordinates['TL_HORI_BRX'], $coordinates['TL_HORI_BRY'], $image, $coordinates['HORI_WIDTH']),
			self::lineVertical($coordinates['TR_VERT_TLX'], $coordinates['TR_VERT_TLY'], $coordinates['TR_VERT_BRX'], $coordinates['TR_VERT_BRY'], $image, $coordinates['VERT_WIDTH'], 'rtl'),
			self::lineHorizontal($coordinates['TR_HORI_TLX'], $coordinates['TR_HORI_TLY'], $coordinates['TR_HORI_BRX'], $coordinates['TR_HORI_BRY'], $image, $coordinates['HORI_WIDTH']),
			self::lineVertical($coordinates['BL_VERT_TLX'], $coordinates['BL_VERT_TLY'], $coordinates['BL_VERT_BRX'], $coordinates['BL_VERT_BRY'], $image, $coordinates['VERT_WIDTH']),
			self::lineHorizontal($coordinates['BL_HORI_TLX'], $coordinates['BL_HORI_TLY'], $coordinates['BL_HORI_BRX'], $coordinates['BL_HORI_BRY'], $image, $coordinates['HORI_WIDTH'], 'btt'),
			self::lineVertical($coordinates['BR_VERT_TLX'], $coordinates['BR_VERT_TLY'], $coordinates['BR_VERT_BRX'], $coordinates['BR_VERT_BRY'], $image, $coordinates['VERT_WIDTH'], 'rtl'),
			self::lineHorizontal($coordinates['BR_HORI_TLX'], $coordinates['BR_HORI_TLY'], $coordinates['BR_HORI_BRX'], $coordinates['BR_HORI_BRY'], $image, $coordinates['HORI_WIDTH'], 'btt'),
		);
	}

	/**
	 * Moves a point
	 *
	 * @param array $point An array containing X and Y coordinates of the point
	 * @param array $offset An array containing X and Y offset values
	 * @return float[] The new X and Y coordinates
	 */
	private static function offset($point, $offset) {
		return array(
			$point[0] + $offset[0],
			$point[1] + $offset[1],
		);
	}

	/**
	 * Sanitize the coordinates of all the detection areas so it fits within the page
	 * 
	 * @param array $coordinates An array containing the 4 corner lines detection areas coordinates
	 * @param resource $image image of type GD
	*/
	private static function sanitizeCoordinates(&$coordinates, $image) {
		$width = imagesx($image);
		$height = imagesy($image);
		$tb = array('t', 'b');
		$lr = array('l', 'r');
		$vh = array('vert', 'hori');
		$ex = array('tlx', 'brx');
		$ey = array('tly', 'bry');
		foreach ($tb as $a) {
			foreach ($lr as $b) {
				foreach ($vh as $c) {
					$vname = "{$a}{$b}_{$c}_";
					foreach ($ex as $d) {
						$vn = strtoupper($vname . $d);
						if ($coordinates[$vn] <= 0) {
							$coordinates[$vn] = 1;
						}
						if ($coordinates[$vn] >= $width) {
							$coordinates[$vn] = $width - 1;
						}
					}
					foreach ($ey as $d) {
						$vn = strtoupper($vname . $d);
						if ($coordinates[$vn] <= 0) {
							$coordinates[$vn] = 1;
						}
						if ($coordinates[$vn] >= $height) {
							$coordinates[$vn] = $height - 1;
						}
					}
				}
			}
		}
	}

	/**
	 * Detect the rotation, scale and offset of the given image 
	 * Use the template page offsets for calculations of scale and offset
	 *
	 * @param resource $image image of type GD
	 * @param array $page template page data
	 * @return array transform data
	 */
	public static function getTransforms($image, $page) {
		$width = imagesx($image);
		$height = imagesy($image);

		self::sanitizeCoordinates($page, $image);

		$corners = self::getCorners($image, $page);

		if (!in_array(false, $corners, true)) { // all edges detected
			$centroid = self::getCentroid($corners);
			$rotation = self::getRotation($corners) - $page['rotation'];
			$scale = self::getScale($page, $corners);
			$offset = self::getOffset($page, $centroid);

			return array(
				'offx' => $offset[0],
				'offy' => $offset[1],
				'scalex' => $scale[0],
				'scaley' => $scale[1],
				'centroidx' => $centroid[0],
				'centroidy' => $centroid[1],
				'rotation' => $rotation,
				'sintheta' => sin($rotation),
				'costheta' => cos($rotation),
				'width' => $width,
				'height' => $height,
			);
		}

		return array( //return no transformation if all edges not detected
			'offx' => 0,
			'offy' => 0,
			'scalex' => 1,
			'scaley' => 1,
			'centroidx' => 0,
			'centroidy' => 0,
			'rotation' => 0,
			'sintheta' => 0,
			'costheta' => 1,
			'width' => $width,
			'height' => $height,
		);
	}

	/**
	 * Apply the given transforms on the coordinates
	 * 
	 * @param array $coordinates top-left and bottom-right coordinates to transform
	 * @param array $transforms the transforms information: offset, centroid, scale, rotation, sin, cos, width and height
	 */
	public static function applyTransforms($coordinates, $transforms) {
		$offset = true;
		$rotate = true;
		$scale = true;
		$new = array();
		$p1 = array($coordinates['tlx'], $coordinates['tly']);
		$p2 = array($coordinates['brx'], $coordinates['bry']);
		if ($offset) {
			$offset = array($transforms['offx'], $transforms['offy']);
			$p1 = self::offset($p1, $offset);
			$p2 = self::offset($p2, $offset);
		}
		$centroid = array($transforms['centroidx'], $transforms['centroidy']);
		if ($rotate) {
			$p1 = self::rotate($transforms['rotation'], $p1, $centroid);
			$p2 = self::rotate($transforms['rotation'], $p2, $centroid);
		}
		if ($scale) {
			$scale = array($transforms['scalex'], $transforms['scaley']);
			$p1 = self::scale($scale, $p1, $centroid);
			$p2 = self::scale($scale, $p2, $centroid);
		}

		list($new['tlx'], $new['tly']) = self::sanitizePoint($p1, $transforms['width'], $transforms['height']);
		list($new['brx'], $new['bry']) = self::sanitizePoint($p2, $transforms['width'], $transforms['height']);

		return array_merge($coordinates, $new);
	}

	/**
	 * Calculate the centroid of an image by finding the intersection of the lines
	 * from top-left to bottom-right and top-right to bottom-left corners
	 *
	 * @param array $corners An array containing the 4 corner lines coordinates
	 * @return array The x and y of the centroid
	 */
	private static function getCentroid($corners) {
		// y = ax + b
		$a = ($corners[7] - $corners[1]) / ($corners[6] - $corners[0]);
		$b = $corners[1] - $a * $corners[0];
		
		// y = xa' + b'
		$al = ($corners[5] - $corners[3]) / ($corners[4] - $corners[2]);
		$bl = $corners[3] - $al * $corners[2];

		$x = ($bl - $b) / ($a - $al);
		$y = $a * $x + $b;

		return array($x, $y);
	}

	/**
	 * Calculate the amount of rotation of an image based on the corner lines coordinates
	 *
	 * @param array $corners An array containing the 4 corner lines coordinates
	 * @return float the rotation angle in radians (positive means conterclockwise rotation)
	 */
	private static function getRotation($corners) {
		$left = $top = $right = $bottom = 0;

		if ($corners[0] != $corners[4]) {
			$left = atan(($corners[4] - $corners[0]) / ($corners[5] - $corners[1]));
		}

		if ($corners[1] != $corners[3]) {
			$top = atan(($corners[1] - $corners[3]) / ($corners[2] - $corners[0]));
		}

		if ($corners[2] != $corners[6]) {
			$right = atan(($corners[6] - $corners[2]) / ($corners[7] - $corners[3]));
		}

		if ($corners[5] != $corners[7]) {
			$bottom = atan(($corners[5] - $corners[7]) / ($corners[6] - $corners[4]));
		}

		return ($left + $top + $right + $bottom) / 4;
	}

	/**
	 * Calculate the new pixel location based on the rotation and centroid
	 * 
	 * @param float $angle in radians
	 * @param array $point An array containing X and Y coordinates of the point
	 * @param array $centroid An array containing X and Y coordinates of the controid
	 * @param float $cos
	 * @param float $sin
	 * @return float[] The new X and Y coordinates
	 */
	private static function rotate($angle, $point, $centroid, $cos = false, $sin = false) {
		// first convert image coordinate to Cartesian coordinate
		$x = $point[0] - $centroid[0];
		$y = $centroid[1] - $point[1];

		if ($angle !== false) {
			$sin = sin($angle);
			$cos = cos($angle);
		}

		$destination = array(
			$cos * $x + $sin * $y,
			$cos * $y - $sin * $x,
		);
	
		// back to image coordinate
		return array(
			$destination[0] + $centroid[0],
			$centroid[1] - $destination[1],
		);
	}

	/**
	 * Calculate the x and y scale of the image based on the corner lines
	 *
	 * @param array $template An array containing the 4 corner lines coordinates of the template
	 * @param array $corners An array containing the 4 corner lines coordinates of the comparing image
	 * @return array The scale factor on the x and y axis
	 */
	private static function getScale($template, $corners) {
		$average = array(
			(($corners[2] - $corners[0]) + ($corners[6] - $corners[4])) / 2,
			(($corners[5] - $corners[1]) + ($corners[7] - $corners[3])) / 2,
		);
		$averageTemplate = array(
			(($template['trx'] - $template['tlx']) + ($template['brx'] - $template['blx'])) / 2,
			(($template['bly'] - $template['tly']) + ($template['bry'] - $template['try'])) / 2,
		);

		return array(
			$averageTemplate[0] === 0 ? 1 : $average[0] / $averageTemplate[0],
			$averageTemplate[1] === 0 ? 1 : $average[1] / $averageTemplate[1],
		);
	}

	/**
	 * Return a new pixel location based on the scale and centroid
	 *
	 * @param array $scale An array containing X and Y scales
	 * @param array $point An array containing X and Y coordinates of the point
	 * @param array $centroid An array containing X and Y coordinates of the controid
	 * @return float[] The new X and Y coordinates
	 */
	private static function scale($scale, $point, $centroid) {
		return array(
			($point[0] - $centroid[0]) * $scale[0] + $centroid[0],
			($point[1] - $centroid[1]) * $scale[1] + $centroid[1],
		);
	}

	/**
	 * Crop an image
	 * 
	 * @param resource $image image of type GD
	 * @param array $area top left and bottom right coordinates
	 * @return resource cropped image of type GD
	 */
	public static function crop($image, $area) {
		$newwidth = $area['brx'] - $area['tlx'];
		$newheight = $area['bry'] - $area['tly'];
		$cropped = imagecreatetruecolor($newwidth, $newheight);
		imagepalettecopy($cropped, $image);
		imagecopyresized($cropped, $image, 0, 0, $area['tlx'], $area['tly'], $newwidth, $newheight, $newwidth, $newheight);
		return $cropped;
	}

	/**
	 * Calculate the fill ratio of an area of an image
	 * 
	 * @param resource $image image of type GD
	 * @param array $area top left and bottom right coorditates
	 * @return float A number between 0 (empty) and 1 (full)
	 */
	public static function fillRatio($image, $area) {
		$total = 0;
		$count = 0;
		for ($x = $area['tlx']; $x < $area['brx']; $x++) {
			for ($y = $area['tly']; $y < $area['bry']; $y++) {
				// 0 - black;
				// 1 - white;
				$color = imagecolorat($image, $x, $y);
				$total += $color;
				$count++;
			}
		}
		if ($count == 0) {
			return 0;
		}
		return 1 - ($total / $count);
	}

	/**
	 * Find a vertical line and return it's position
	 * 
	 * @param int $tlx
	 * @param int $tly
	 * @param int $brx
	 * @param int $bry
	 * @param resource $image
	 * @param int $lineWidth
	 * @param string $direction the direction of search: ltr (left to right) or rtl (right to left)
	 * @return boolean|int The X coordinate of found line or false if not found
	 */
	private static function lineVertical($tlx, $tly, $brx, $bry, $image, $lineWidth, $direction = 'ltr') {
		$foundCount = array();
		// iterate 10 times to find the line
		$increment = self::divideInt($bry - $tly, 10);
		$tolerance = self::divideInt($lineWidth, 3);

		// lines loop
		for ($y = $tly; $y < $bry; $y += $increment) {
			//0 is black, 1 is white
			$color = 1;
			$width = 0;
			$start = $tlx;
			// iterate line, if find black pixels sequence count the X coordinate
			for ($x = $tlx; $x < $brx; $x++) {
				$rgb = imagecolorat($image, $x, $y);
				if ($rgb != $color) {
					if ($color == 0 && $width >= $lineWidth - $tolerance && $width <= $lineWidth + $tolerance) {
						$found = (int) $start + self::divideInt($width, 2);
						if (!array_key_exists($found, $foundCount)) {
							$foundCount[$found] = 1;
						} else {
							$foundCount[$found]++;
						}
					}
					$width = 0;
					$color = $rgb;
					$start = $x;
				}
				$width++;
			}
		}

		if (empty($foundCount)) {
			return false;
		}

		// return the highest/lowest X (key) where count (value) is the highest
		if ($direction === 'rtl') {
			krsort($foundCount);
		} else {
			ksort($foundCount);
		}
		return array_search(max($foundCount), $foundCount);
	}

	/**
	 * Find a horizontal line and return it's position
	 * 
	 * @param int $tlx
	 * @param int $tly
	 * @param int $brx
	 * @param int $bry
	 * @param resource $image
	 * @param int $lineWidth
	 * @param string $direction the direction of search: ttb (top to bottom) or btt (bottom to top)
	 * @return boolean|int The Y coordinate of found line or false if not found
	 */
	private static function lineHorizontal($tlx, $tly, $brx, $bry, $image, $lineWidth, $direction = 'ttb') {
		$foundCount = array();
		// iterate 10 times to find the line
		$increment = self::divideInt(($brx - $tlx), 10);
		$tolerance = self::divideInt($lineWidth, 3);

		// lines loop
		for ($x = $tlx; $x < $brx; $x += $increment) {
			//0 is black, 1 is white
			$color = 1;
			$width = 0;
			$start = $tly;
			// iterate vertical lines, if find black pixels sequence count the Y coordinate
			for ($y = $tly; $y < $bry; $y++) {
				$rgb = imagecolorat($image, $x, $y);
				if ($rgb != $color) {
					if ($color == 0 && $width >= $lineWidth - $tolerance && $width <= $lineWidth + $tolerance) {
						$found = (int) $start + self::divideInt($width, 2);
						if (!array_key_exists($found, $foundCount)) {
							$foundCount[$found] = 1;
						} else {
							$foundCount[$found] ++;
						}
					}
					$width = 0;
					$color = $rgb;
					$start = $y;
				}
				$width++;
			}
		}

		// return the highest/lowest Y (key) where count (value) is the highest
		if ($direction === 'btt') {
			krsort($foundCount);
		} else {
			ksort($foundCount);
		}
		return array_search(max($foundCount), $foundCount);
	}

	/**
	 * Overlay an image on all the coordinates given
	 * 
	 * @param resource $image image of type GD
	 * @param array $boxes An array of top left and bottom right coordinates
	 * @return resource overlayed image of type GD
	 */
	public static function overlay($image, $boxes) {
		$sizex = imagesx($image);
		$sizey = imagesy($image);

		// Convert the Image to PNG-24 (for alpha blending)
		$imageTrueColor = imagecreatetruecolor($sizex, $sizey);
		imagecopy($imageTrueColor, $image, 0, 0, 0, 0, $sizex, $sizey);

		// orange overlay colour
		$color = imagecolorallocatealpha($imageTrueColor, 255, 0, 0, 75);

		foreach ($boxes as $box) {
			imagefilledrectangle($imageTrueColor, $box['tlx'], $box['tly'], $box['brx'], $box['bry'], $color);
		}

		return $imageTrueColor;
	}

	/**
	 * If the split scanning is enabled verify and split the image
	 * 
	 * @param resource $image image of type GD
	 * @return resource[] images of type GD
	 */
	public static function split($image) {
		if (SPLIT_SCANNING) {
			$width = imagesx($image);
			$height = imagesy($image);
			$swidth = $width / 2.0;

			// if image is side by side double the page size, it needs to be split
			if ((PAGE_WIDTH - SPLIT_SCANNING_THRESHOLD) < $swidth && $swidth < (PAGE_WIDTH + SPLIT_SCANNING_THRESHOLD)) {
				return array(
					crop($image, array('tlx' => 0, 'tly' => 0, 'brx' => $swidth, 'bry' => $height)),
					crop($image, array('tlx' => $swidth, 'tly' => 0, 'brx' => $width, 'bry' => $height)),
				);
			}
		}

		return array($image);
	}

}

// theses functions were keep for backward compatibility,
// while the other modules are not updates

function is_blank_page($image, $page) {
	return Image::isPageBlank($image, $page);
}

function offset($image, $a, $compare, $coordinates) {
	return Image::getCorners($image, $coordinates);
}

function detecttransforms($image, $page) {
	return Image::getTransforms($image, $page);
}

function applytransforms($coordinates, $transforms) {
	return Image::applyTransforms($coordinates, $transforms);
}

function crop($image, $area) {
	return Image::crop($image, $area);
}

function fillratio($image, $area) {
	return Image::fillRatio($image, $area);
}

function overlay($image, $boxes) {
	return Image::overlay($image, $boxes);
}

function split_scanning($image) {
	return Image::split($image);
}
