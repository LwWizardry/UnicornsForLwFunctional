<?php

namespace MP\Helpers;

use Imagick;
use ImagickException;
use MP\ErrorHandling\BadRequestException;

class ImageWrapper {
	private int $width;
	private int $height;
	private string $extension;
	private string $bytes;
	private string $hash;
	
	public function __construct(string $base64) {
		//### Validate image: ###
		
		//Convert Base64 back to bytes:
		$decode = base64_decode($base64);
		if($decode === false) {
			throw new BadRequestException('Base64 image encoding invalid.');
		}
		//Create Imagick instance:
		$imagick = new Imagick();
		//Try to parse the image, if this fails, the image is invalid or broken!
		try {
			$imagick->readImageBlob($decode);
		} catch (ImagickException) {
			throw new BadRequestException('Provided image is invalid and cannot be used!');
		}
		
		//### Format image: ###
		
		//Fix rotation of JPEG images:
		$orientation = $imagick->getImageOrientation();
		if (!empty($orientation)) {
			switch ($orientation) {
				case imagick::ORIENTATION_BOTTOMRIGHT:
					$imagick->rotateimage("#000", 180);
					break;
				case imagick::ORIENTATION_RIGHTTOP:
					$imagick->rotateimage("#000", 90);
					break;
				case imagick::ORIENTATION_LEFTBOTTOM:
					$imagick->rotateimage("#000", -90);
					break;
			}
		}
		
		//Remove all JPEG EXIF data (comments and whatever):
		$imagick->stripImage();
		
		//### Extract information from image: ###
		
		//Get the "file"-type of the image:
		$format = $imagick->getImageFormat();
		$this->extension = match ($format) {
			'PNG' => "png",
			'JPEG' => "jpg",
			'GIF' => "gif",
			'WEBP' => 'webp',
			default => throw new BadRequestException('Unsupported image type ' . $format . ' supported are: JPEG/PNG/GIF/WEBP'),
		};
		
		//Reset pointer, so that the right image size can be detected:
		$imagick->setFirstIterator(); //Should work with all formats...
		//Get the resolution of the image (do bounds check):
		$size = $imagick->getImageGeometry();
		$this->width = $size['width'];
		$this->height = $size['height'];
		
		//Write back the image to bytes, to be sure, that the image is valid:
		$this->bytes = $imagick->getImagesBlob();
		$imagick->destroy(); //Clean up!
		$this->hash = hash('sha256', $this->bytes);
	}
	
	/**
	 * @return string
	 */
	public function getHash(): string {
		return $this->hash;
	}
	
	/**
	 * @return string
	 */
	public function getExtension(): string {
		return $this->extension;
	}
	
	/**
	 * @return int
	 */
	public function getWidth(): int {
		return $this->width;
	}
	
	/**
	 * @return int
	 */
	public function getHeight(): int {
		return $this->height;
	}
	
	/**
	 * @return string
	 */
	public function getBytes(): string {
		return $this->bytes;
	}
}