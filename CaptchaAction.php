<?php

namespace hr\captcha;

use Yii;
use yii\web\Response;
use yii\helpers\Url;

/**
 * Description of CaptchaAction
 *
 * @author Henk Reitsma <henkreitsma1990@gmail.com>
 * @since 1.0
 */
class CaptchaAction extends \yii\captcha\CaptchaAction
{
    const JPEG_FORMAT = 'jpeg';
    const PNG_FORMAT = 'png';

	/**
     * Max value of variables.
     * @var int
     */
    public $maxValue = 10;
	
    /**
     * Avaliable values are 'jpeg' or 'png'
     * @var string 
     */
    public $imageFormat = self::PNG_FORMAT;
	
	/**
     * Font size.
     * @var int
     */
    public $size = 14;

    /**
     * @inheritdoc
     */
    public function init()
    {
        
    }

    /**
     * @inheritdoc
     */
    public function run()
    {
        if (Yii::$app->request->getQueryParam(self::REFRESH_GET_VAR) !== null) {
            // AJAX request for regenerating code
            $code = $this->getVerifyCode(true);
            Yii::$app->response->format = Response::FORMAT_JSON;
            return [
                'hash1' => $this->generateValidationHash($code),
                'hash2' => $this->generateValidationHash(strtolower($code)),
                // we add a random 'v' parameter so that FireFox can refresh the image
                // when src attribute of image tag is changed
                'url' => Url::to([$this->id, 'v' => uniqid()]),
            ];
        } else {
            $this->setHttpHeaders();
            Yii::$app->response->format = Response::FORMAT_RAW;
            return $this->renderImage($this->getVerifyCode(false, true));
        }
    }

    /**
     * @inheritdoc
     */
    public function getVerifyCode($regenerate = false, $code = false)
    {
        $session = Yii::$app->getSession();
        $session->open();
        $name = $this->getSessionKey();
        if ($session[$name] === null || $regenerate) {
            $session[$name . 'code'] = $this->generateVerifyCode();
            $session[$name] = $this->getValue($session[$name . 'code']);
            $session[$name . 'count'] = 1;
        }

        return $code ? $session[$name . 'code'] : $session[$name];
    }

    /**
     * @inheritdoc
     */
    public function validate($input)
    {
        $code = $this->getVerifyCode(false, true);
        $value = $this->getValue($code);
        
        $valid = $input == $value;

        $session = Yii::$app->getSession();
        $session->open();
        $name = $this->getSessionKey() . 'count';
        $session[$name] = $session[$name] + 1;
        if ($valid || $session[$name] > $this->testLimit && $this->testLimit > 0) {
            $this->getVerifyCode(true);
        }

        return $valid;
    }

    /**
     * @inheritdoc
     */
    protected function generateVerifyCode()
    {
        mt_srand(time());
		
		$code = [
			mt_rand(1, $this->maxValue),
			mt_rand(0, $this->maxValue)
		];

        return $code;
    }

    /**
     * @inheritdoc
     */
    protected function renderImage($code)
    {
        require __DIR__ . '/mathpublisher.php';

        $formula = new \expression_math(tableau_expression(trim($this->getExpression($code))));
        $formula->dessine($this->size);

        ob_start();
        switch ($this->imageFormat) {
            case self::JPEG_FORMAT:
                imagejpeg($formula->image);
                break;
            case self::PNG_FORMAT:
                imagepng($formula->image);
                break;
        }
        imagedestroy($formula->image);

        return ob_get_clean();
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
            ->set('Content-type', "image/{$this->imageFormat}");
    }

    /**
     * Get expresion formula .
     * @param array $code
     * @return string
     */
    protected function getExpression($code)
    {
        if ($this->fixedVerifyCode !== null) {
            return $this->fixedVerifyCode;
        }
		
		return "{$code[0]}~+~{$code[1]}~=";
    }

    /**
     * Get value of formula
     * @param array $code
     * @return int|float
     */
    protected function getValue($code)
    {
        if ($this->fixedVerifyCode !== null) {
            return $this->fixedVerifyCode;
        }
		
		return $code[0] + $code[1];
    }
}
