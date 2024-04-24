<?php
/**
 *--------------------------------------------------------------------
 *
 * Holds the drawing $im
 * You can use get_im() to add other kind of form not held into these classes.
 *
 *--------------------------------------------------------------------
 * Copyright (C) Jean-Sebastien Goupil
 * http://www.barcodephp.com
 */
include_once('BCGBarcode.php');
include_once('drawer/BCGDrawJPG.php');
include_once('drawer/BCGDrawPNG.php');

class BCGDrawing {
    const IMG_FORMAT_PNG = 1;
    const IMG_FORMAT_JPEG = 2;
    const IMG_FORMAT_GIF = 3;
    const IMG_FORMAT_WBMP = 4;

    private $w, $h;         // int
    private $color;         // BCGColor
    private $filename;      // char *
    private $im;            // {object}
    private $barcode;       // BCGBarcode
    private $dpi;           // int
    private $rotateDegree;  // float

    /**
     * Constructor.
     *
     * @param int $w
     * @param int $h
     * @param string filename
     * @param BCGColor $color
     */
    public function __construct( BCGColor $color, $filename = null) {
        $this->im = null;
        $this->setFilename($filename);
        $this->color = $color;
        $this->dpi = null;
        $this->rotateDegree = 0.0;
    }

    /**
     * Destructor.
     */
    public function __destruct() {
        $this->destroy();
    }

    /**
     * Gets the filename.
     *
     * @return string
     */
    public function getFilename() {
        return $this->filename;
    }

    /**
     * Sets the filename.
     *
     * @param string $filaneme
     */
    public function setFilename($filename) {
        $this->filename = $filename;
    }

    /**
     * @return resource.
     */
    public function get_im() {
        return $this->im;
    }

    /**
     * Sets the image.
     *
     * @param resource $im
     */
    public function set_im($im) {
        $this->im = $im;
    }

    /**
     * Gets barcode for drawing.
     *
     * @return BCGBarcode
     */
    public function getBarcode() {
        return $this->barcode;
    }

    /**
     * Sets barcode for drawing.
     *
     * @param BCGBarcode $barcode
     */
    public function setBarcode(BCGBarcode $barcode) {
        $this->barcode = $barcode;
    }

    /**
     * Gets the DPI for supported filetype.
     *
     * @return int
     */
    public function getDPI() {
        return $this->dpi;
    }

    /**
     * Sets the DPI for supported filetype.
     *
     * @param float $dpi
     */
    public function setDPI($dpi) {
        $this->dpi = $dpi;
    }

    /**
     * Gets the rotation angle in degree.
     *
     * @return float
     */
    public function getRotationAngle() {
        return $this->rotateDegree;
    }

    /**
     * Sets the rotation angle in degree.
     *
     * @param float $degree
     */
    public function setRotationAngle($degree) {
        $this->rotateDegree = (float)$degree;
    }

    /**
     * Draws the barcode on the image $im.
     */
    public function draw() {
        $size = $this->barcode->getDimension(0, 0);
        $this->w = max(1, $size[0]);
        $this->h = max(1, $size[1]);
        $this->init();
        $this->barcode->draw($this->im);
    }

    //ajout FRED
    function oblady_rotateImage($img, $rotation) {
        $width = imagesx($img);
        $height = imagesy($img);
        switch($rotation) {
            case 90: $newimg= @imagecreatetruecolor($height , $width );break;
            case 180: $newimg= @imagecreatetruecolor($width , $height );break;
            case 270: $newimg= @imagecreatetruecolor($height , $width );break;
            case 0: return $img;break;
            case 360: return $img;break;
        }
        if($newimg) {
            for($i = 0;$i < $width ; $i++) {
            for($j = 0;$j < $height ; $j++) {
                $reference = imagecolorat($img,$i,$j);
                switch($rotation) {
                case 90: if(!@imagesetpixel($newimg, ($height - 1) - $j, $i, $reference )){return false;}break;
                case 180: if(!@imagesetpixel($newimg, $width - $i, ($height - 1) - $j, $reference )){return false;}break;
                case 270: if(!@imagesetpixel($newimg, $j, $width - $i, $reference )){return false;}break;
                }
            }
            } return $newimg;
        }
        return false;
    }
    
    /**
     * Saves $im into the file (many format available).
     *
     * @param int $image_style
     * @param int $quality
     */
    public function finish($image_style = self::IMG_FORMAT_PNG, $quality = 100) {
        $drawer = null;

        
        $im = $this->im;
        if ($this->rotateDegree > 0.0) {
            if (function_exists('imagerotate')) {
                $im = imagerotate($this->im, $this->rotateDegree, $this->color->allocate($this->im));
            } else {
                //modif fred 
                $this->im = $this->oblady_rotateImage($this->im, $this->rotateDegree);
                //throw new BCGDrawException('The method imagerotate doesn\'t exist on your server. Do not use any rotation.');
            }
        }

        if ($image_style === self::IMG_FORMAT_PNG) {
            $drawer = new BCGDrawPNG($im);
            $drawer->setFilename($this->filename);
            $drawer->setDPI($this->dpi);
        } elseif ($image_style === self::IMG_FORMAT_JPEG) {
            $drawer = new BCGDrawJPG($im);
            $drawer->setFilename($this->filename);
            $drawer->setDPI($this->dpi);
            $drawer->setQuality($quality);
        } elseif ($image_style === self::IMG_FORMAT_GIF) {
            // Some PHP versions have a bug if passing 2nd argument as null.
            if ($this->filename === null || $this->filename === '') {
                imagegif($im);
            } else {
                imagegif($im, $this->filename);
            }
        } elseif ($image_style === self::IMG_FORMAT_WBMP) {
            imagewbmp($im, $this->filename);
        }

        if ($drawer !== null) {
            $drawer->draw();
        }
    }

    /**
     * Writes the Error on the picture.
     *
     * @param Exception $exception
     */
    public function drawException($exception) {
        $this->w = 1;
        $this->h = 1;
        $this->init();

        // Is the image big enough?
        $w = imagesx($this->im);
        $h = imagesy($this->im);

        $text = 'Error: ' . $exception->getMessage();

        $width = imagefontwidth(2) * strlen($text);
        $height = imagefontheight(2);
        if ($width > $w || $height > $h) {
            $width = max($w, $width);
            $height = max($h, $height);

            // We change the size of the image
            $newimg = imagecreatetruecolor($width, $height);
            imagefill($newimg, 0, 0, imagecolorat($this->im, 0, 0));
            imagecopy($newimg, $this->im, 0, 0, 0, 0, $w, $h);
            $this->im = $newimg;
        }

        $black = new BCGColor('black');
        imagestring($this->im, 2, 0, 0, $text, $black->allocate($this->im));
    }

    /**
     * Free the memory of PHP (called also by destructor).
     */
    public function destroy() {
        @imagedestroy($this->im);
    }

    /**
     * Init Image and color background.
     */
    private function init() {
        if ($this->im === null) {
            $this->im = imagecreatetruecolor((int) $this->w, (int) $this->h)
            or die('Can\'t Initialize the GD Libraty');
            imagefilledrectangle($this->im, 0, 0, (int) $this->w - 1, (int) $this->h - 1, $this->color->allocate($this->im));
        }
    }
}
?>