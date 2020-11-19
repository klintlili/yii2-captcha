<?php

namespace klintlili\captcha;

use yii\base\InvalidConfigException;
use yii\helpers\Json;
use yii\widgets\InputWidget;
use yii\helpers\Html;
use yii\helpers\Url;

/**
 * 
 */
class Captcha extends InputWidget
{
    /**
     * @var string|array the route of the action that generates the CAPTCHA images.
     * The action represented by this route must be an action of [[CaptchaAction]].
     * Please refer to [[\yii\helpers\Url::toRoute()]] for acceptable formats.
     */
    public $captchaAction = 'site/captcha';

    /**
     * @var string the template for arranging the CAPTCHA image tag and the text input tag.
     * In this template, the token `{image}` will be replaced with the actual image tag,
     * while `{input}` will be replaced with the text input tag.
     */
    public $template = '{image} {input}';

    /**
     * @var array HTML attributes to be applied to the CAPTCHA image tag.
     * @see \yii\helpers\Html::renderTagAttributes() for details on how attributes are being rendered.
     */
    public $imageOptions = [];


    /**
     * @var array the HTML attributes for the input tag.
     * @see \yii\helpers\Html::renderTagAttributes() for details on how attributes are being rendered.
     */
    public $options = ['class' => 'form-control'];


    /**
     * Initializes the widget.
     */
    public function init()
    {
        parent::init();

        static::checkRequirements();

        if (!isset($this->imageOptions['id'])) {
            $this->imageOptions['id'] = $this->options['id'] . '-image';
        }
    }

    /**
     * Renders the widget.
     */
    public function run()
    {
        $route = $this->captchaAction;
        if (is_array($route)) {
            $route['v'] = uniqid('', true);
            $route['ts'] = time();
        } else {
            $route = [$route, 'v' => uniqid('', true), 'ts' => time()];
        }
        $this->registerClientScript($route);
        $input = $this->renderInputHtml('text');
        $image = Html::img($route, $this->imageOptions);
        echo strtr($this->template, [
            '{input}' => $input,
            '{image}' => $image,
        ]);
    }

    /**
     * Registers the needed JavaScript.
     */
    public function registerClientScript($route)
    {
        $id = $this->imageOptions['id'];
        unset($route['ts']);
        $url = Url::to($route);
        $view = $this->getView();
        $js = <<<EOF
function getQueryVariable(query, variable)
{
   var vars = query.split("&");
   for (var i=0;i<vars.length;i++) {
           var pair = vars[i].split("=");
           if(pair[0] == variable){return pair[1];}
   }
   return(false);
}
function getCode() {
    var ts = Date.parse(new Date()) / 1000;
    var odlts = $('#$id').attr("src");
    if(odlts){
        odlts = getQueryVariable(odlts, 'ts');
        console.log(odlts);
        if(ts-odlts<2){
            return false;
        }
    }

    $('#$id').attr("src", "$url&ts=" + ts);
}
$(document).on('click', '#$id', function(event) {
    event.preventDefault();
    getCode();
});
EOF;
        $view->registerJs($js);
    }

    /**
     * Checks if there is graphic extension available to generate CAPTCHA images.
     * This method will check the existence of ImageMagick and GD extensions.
     * @return string the name of the graphic extension, either "imagick" or "gd".
     * @throws InvalidConfigException if neither ImageMagick nor GD is installed.
     */
    public static function checkRequirements()
    {
        if (extension_loaded('imagick')) {
            $imagickFormats = (new \Imagick())->queryFormats('PNG');
            if (in_array('PNG', $imagickFormats, true)) {
                return 'imagick';
            }
        }
        if (extension_loaded('gd')) {
            $gdInfo = gd_info();
            if (!empty($gdInfo['FreeType Support'])) {
                return 'gd';
            }
        }
        throw new InvalidConfigException('Either GD PHP extension with FreeType support or ImageMagick PHP extension with PNG support is required.');
    }
}
