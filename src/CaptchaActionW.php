<?php

namespace klintlili\captcha;

use Yii;
use Gregwar\Captcha\CaptchaBuilder;
use yii\base\Action;
use Gregwar\Captcha\PhraseBuilder;
use yii\helpers\Url;
use yii\web\Response;

class CaptchaActionW extends Action
{
    /**
     * The name of the GET parameter indicating whether the CAPTCHA image should be regenerated.
     */
    const REFRESH_GET_VAR = 'refresh';

    /**
     * Captcha image width
     *
     * @var integer
     */
    public $width = 150;

    /**
     * Captcha image height
     *
     * @var integer
     */
    public $height = 40;

    /**
     * Captcha character count
     *
     * @var integer
     */
    public $length = 5;

    /**
     * Generated image quality
     * @var integer
     */
    public $quality = 80;

    /**
     * CaptchaBuilder instance
     *
     * @var \Gregwar\Captcha\CaptchaBuilder
     */
    protected $captchaBuilder;

    /**
     * PhraseBuilder instance
     *
     * @var \Gregwar\Captcha\PhraseBuilder
     */
    protected $phraseBuilder;


    public function init()
    {
        parent::init();

        $this->phraseBuilder = new PhraseBuilder($this->length);
        $this->captchaBuilder = new CaptchaBuilder(null, $this->phraseBuilder);
    }

    public function run()
    {
        if (Yii::$app->request->getQueryParam(self::REFRESH_GET_VAR) !== null) {
            $this->captchaBuilder->build($this->width, $this->height);
            $this->saveCaptcha();
            // AJAX request for regenerating code
            $code = $this->getCaptchaPhrase();
            Yii::$app->response->format = Response::FORMAT_JSON;
            $v = uniqid('', true);
            Yii::$app->session->set($this->getSessionKey().'_'.self::REFRESH_GET_VAR.'_v', $v);
            return [
                'hash1' => $this->generateValidationHash($code),
                //'code' => $code,
                'hash2' => $this->generateValidationHash(strtolower($code)),
                // we add a random 'v' parameter so that FireFox can refresh the image
                // when src attribute of image tag is changed
                'url' => Url::to([$this->id, 'v' => $v]),
            ];
        }else{
            $session_v = Yii::$app->session->get($this->getSessionKey().'_'.self::REFRESH_GET_VAR.'_v');
            if($session_v && $session_v == Yii::$app->request->get('v') && $this->getCaptchaPhrase()){
                $this->captchaBuilder = new CaptchaBuilder($this->getCaptchaPhrase());
                $this->captchaBuilder->build($this->width, $this->height);
            }else{
                $this->captchaBuilder->build($this->width, $this->height);
                $this->saveCaptcha();
            }

            $this->setHttpHeaders();

            Yii::$app->response->format = \yii\web\Response::FORMAT_RAW;
            return $this->captchaBuilder->get($this->quality);
        }
    }

    /**
     * Generates a hash code that can be used for client-side validation.
     * @param string $code the CAPTCHA code
     * @return string a hash code generated from the CAPTCHA code
     */
    public function generateValidationHash($code)
    {
        for ($h = 0, $i = strlen($code) - 1; $i >= 0; --$i) {
            $h += ord($code[$i]);
        }

        return $h;
    }

    /**
     * Saves CAPTCHA phrase to session
     *
     * @return void
     */
    protected function saveCaptcha()
    {
        $captchaPhrase = $this->captchaBuilder->getPhrase();
        $sessionKey = $this->getSessionKey();

        Yii::$app->session->set($sessionKey, $captchaPhrase);
    }

    /**
     * Returns the session variable name used to store verification code.
     * @return string the session variable name
     */
    protected function getSessionKey()
    {
        return '__captcha_' . $this->getUniqueId();
    }

    /**
     * Returns CAPTCHA phrase for validation
     *
     * @return string
     */
    public function getCaptchaPhrase()
    {
        $sessionKey = $this->getSessionKey();
        $captchaPhrase = Yii::$app->session->get($sessionKey);

        return $captchaPhrase;
    }

    /**
     * Validates the input to see if it matches the generated code.
     * @param string $input user input
     * @param bool $caseSensitive whether the comparison should be case-sensitive
     * @return bool whether the input is valid
     */
    public function validate($input, $caseSensitive)
    {
        $captchaPhrase = $this->getCaptchaPhrase();
        $valid = $caseSensitive ? ($input === $captchaPhrase) : strcasecmp($input, $captchaPhrase) === 0;

        return $valid;
    }

    /**
     * Sets the HTTP headers needed by image response.
     */
    protected function setHttpHeaders()
    {
        Yii::$app->getResponse()->getHeaders()
            ->set('Pragma', 'public')
            ->set('Expires', '0')
            ->set('Cache-Control', 'must-revalidate, post-check=0, pre-check=0')
            ->set('Content-Transfer-Encoding', 'binary')
            ->set('Content-type', 'image/jpeg');
    }
}