<?php

include("color.class.php");

class MockImage
{
    // 程序名称
    private $AppName = "MockImage";
    // 默认生成图片参数
    private $config = [
        "background" => "ccc",
        "foreground" => "000",
        "fileFormat" => "gif",
        "width"      => 1,
        "height"     => 1
    ];
    private $background = null;
    private $foreground = null;
    private $fileFormat = null;
    private $width = null;
    private $height = null;
    private $text = "";
    private $image = null;

    function __construct()
    {

        if (!isset($_GET["query"])) {
            self::fail("Can't find params.");
        }

        $params = self::getParams($_GET["query"]);

        // 设置图片的宽和高
        // 支持 640x480 640x4:3 4:3x480
        self::setDimensions($params["dimensions"]);

        // 设置图片的前景色和背景色
        self::setImageProp($params["background"], $params["foreground"]);

        // 创建一个图片对象
        self::createImage();

        // 设置图片格式
        self::setFileFormat($params["format"]);

        // 设置文本内容
        self::setTextProp($params["text"]);

        // 生成图片内容
        self::generateImage();
    }

    function __destruct()
    {
        imageDestroy($this->image);
    }


    /**
     * 整理请求参数
     *
     * @param $query
     *
     * @return array
     */
    private function getParams($query)
    {
        // 从URI中获取参数
        $params = explode('/', strtolower($query));
        $paramsCount = count($params);

        if ($paramsCount === 0) {
            self::fail("No params.");
        }

        $result = [
            "format"     => $this->config["fileFormat"],
            "text"       => "",
            "background" => $this->config["background"],
            "foreground" => $this->config["foreground"],
        ];

        // 获取最后的一个参数
        $lastParam = $params[ $paramsCount - 1 ];
        // 查看最后一个参数是否存在文件类型参数
        if (false !== strpos($lastParam, ".")) {
            $mixParams = explode(".", $lastParam);
            // 不允许存在`query.params.png`形式
            if (count($mixParams) > 2) {
                self::fail("Check request format param..");
            }
            // 最后一个参数存在`.format`字符串时，将该参数修复为单个
            $params[ $paramsCount - 1 ] = $mixParams[0];
            // 文件格式
            $result["format"] = $mixParams[1];
        }

        // 尺寸信息
        $result["dimensions"] = $params[0];

        if (isset($_GET['text'])) {
            $result["text"] = $_GET['text'];
        }

        if ($paramsCount > 1) {
            $result['background'] = $params[1];
        }
        if ($paramsCount > 2) {
            $result['foreground'] = $params[2];
        }

        return $result;
    }

    /**
     * 设置图片的宽和高
     *
     * @param $param
     */
    private function setDimensions($param)
    {
        // 获取长宽
        $dimensions = explode("x", $param);
        switch (count($dimensions)) {
            case 0:
                $this->width = $this->config["width"];
                $this->height = $this->config["height"];
                break;
            case 1:
                $this->width = $dimensions[0];
                $this->height = $dimensions[0];
                break;
            case 2:
                $this->width = $dimensions[0];
                $this->height = $dimensions[1];
                break;
            default:
                header($this->AppName . ":Dimensions are invalid.");
                exit;
        }

        // 检查是否要根据比例生成图片
        if (false !== strpos($param, ":")) {
            if (false !== strpos($this->width, ":")) {
                $ratio = explode(":", $this->width);
                if (count($ratio) === 2) {
                    $this->height = intval($this->height);
                    if ($this->height === 0) {
                        self::fail("Height is invalid.");
                    }
                    $this->width = ($this->height * $ratio[0]) / $ratio[1];
                } else {
                    self::fail("Ratio is invalid.");
                }
            } else {
                $ratio = explode(":", $this->height);
                if (count($ratio) === 2) {
                    $this->width = intval($this->width);
                    if ($this->width === 0) {
                        self::fail("Width is invalid.");
                    }
                    $this->height = ($this->width * $ratio[1]) / $ratio[0];
                } else {
                    self::fail("Ratio is invalid.");
                }
            }
        } else {
            $this->width = intval($this->width);
            $this->height = intval($this->height);
            if ($this->width === 0 || $this->height === 0) {
                self::fail("Dimensions are invalid.");
            }
        }

        // 小数对于WEB图片无意义
        $this->width = round($this->width);
        $this->height = round($this->height);

        $area = $this->width * $this->height;
        if ($area >= 16000000 || $this->width > 9999 || $this->height > 9999) {
            self::fail("Image is too large.");
        }
    }

    /**
     * 设置图片基础属性
     *
     * @param $background
     * @param $foreground
     */
    private function setImageProp($background, $foreground)
    {
        $this->background = new color();
        $this->background->set_hex($background);

        $this->foreground = new color();
        $this->foreground->set_hex($foreground);
    }

    /**
     * 创建一个图片对象
     */
    private function createImage()
    {
        $this->image = imageCreate($this->width, $this->height);
        $this->background = imageColorAllocate(
            $this->image,
            $this->background->get_rgb('r'),
            $this->background->get_rgb('g'),
            $this->background->get_rgb('b')
        );
        $this->foreground = imageColorAllocate(
            $this->image,
            $this->foreground->get_rgb('r'),
            $this->foreground->get_rgb('g'),
            $this->foreground->get_rgb('b')
        );
    }

    /**
     * 设置图片格式
     *
     * @param $format
     */
    private function setFileFormat($format)
    {
        if (in_array($format, ["gif", "png", "jpg", "jpeg"])) {
            $this->fileFormat = $format;
        } else {
            self::fail("Not support format:" . $format);
        }
    }

    /**
     * 设置文本样式和属性
     *
     * @author Russell Heimlich
     *
     * @param $content
     */
    private function setTextProp($content)
    {
        if ($content) {
            $this->text = preg_replace("#(0x[0-9A-F]{2})#e", "chr(hexdec('\\1'))", $content);
        } else {
            $this->text = $this->width . " x " . $this->height;
        }

        $font = FONT_PATH;
        //I don't use this but if you wanted to angle your text you would change it here.
        $text_angle = 0;

        //scale the text size based on the smaller of width/8 or hieght/2 with a minimum size of 5.
        $fontsize = max(min($this->width / strlen($this->text) * 1.15, $this->height * 0.5), 5);

        //Pass these variable to a function that calculates the position of the bounding box.
        $textBox = self::imagettfbbox_t($fontsize, $text_angle, $font, $this->text);

        //Calculates the width of the text box by subtracting the Upper Right "X" position with the Lower Left "X" position.
        $textWidth = ceil(($textBox[4] - $textBox[1]) * 1.07);

        //Calculates the height of the text box by adding the absolute value of the Upper Left "Y" position with the Lower Left "Y" position.
        $textHeight = ceil((abs($textBox[7]) + abs($textBox[1])) * 1);

        //Determines where to set the X position of the text box so it is centered.
        $textX = ceil(($this->width - $textWidth) / 2);
        //Determines where to set the Y position of the text box so it is centered.
        $textY = ceil(($this->height - $textHeight) / 2 + $textHeight);

        //Creates the rectangle with the specified background color. http://us2.php.net/manual/en/function.imagefilledrectangle.php
        imageFilledRectangle($this->image, 0, 0, $this->width, $this->height, $this->background);

        //Create and positions the text http://us2.php.net/manual/en/function.imagettftext.php
        imagettftext($this->image, $fontsize, $text_angle, $textX, $textY, $this->foreground, $font, $this->text);

    }

    /**
     * 根据文件类型生成图片
     */
    private function generateImage()
    {
        $offset = 60 * 60 * 24 * 14;
        $ExpStr = "Expires: " . gmdate("D, d M Y H:i:s", time() + $offset) . " GMT";

        header($ExpStr); //Set a far future expire date. This keeps the image locally cached by the user for less hits to the server.
        header('Cache-Control:	max-age=120');
        header("Last-Modified: " . gmdate("D, d M Y H:i:s", time() - $offset) . " GMT");
        header('Content-type: image/' . $this->fileFormat); //Set the header so the browser can interpret it as an image and not a bunch of weird text.

        switch ($this->fileFormat) {
            case 'gif':
                imagegif($this->image);
                break;
            case 'png':
                imagepng($this->image);
                break;
            case 'jpg':
            case 'jpeg':
                imagejpeg($this->image);
                break;
        }
    }

    /**
     * 输出错误信息
     *
     * @param $message
     */
    private function fail($message)
    {
        header($this->AppName . ":" . $message);
        exit;
    }


    /**
     * Fix PHP imagettfbbox Bugs - Ruquay K Calloway
     *
     * @author Ruquay K Calloway
     * @link   http://ruquay.com/sandbox/imagettf/
     *
     * @param $size
     * @param $text_angle
     * @param $fontfile
     * @param $text
     *
     * @return array
     */
    private function imagettfbbox_t($size, $text_angle, $fontfile, $text)
    {
        // compute size with a zero angle
        $coords = imagettfbbox($size, 0, $fontfile, $text);

        // convert angle to radians
        $a = deg2rad($text_angle);

        // compute some usefull values
        $ca = cos($a);
        $sa = sin($a);
        $ret = [];

        // perform transformations
        for ($i = 0; $i < 7; $i += 2) {
            $ret[ $i ] = round($coords[ $i ] * $ca + $coords[ $i + 1 ] * $sa);
            $ret[ $i + 1 ] = round($coords[ $i + 1 ] * $ca - $coords[ $i ] * $sa);
        }

        return $ret;
    }

}