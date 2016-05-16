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
     * One of these operators will be used.
     * @var int
     */
    public $operators = ['+'];
	
	/**
     * Min value of variables.
     * @var int
     */
    public $minValue = 1;
	
	/**
     * Max value of variables.
     * @var int
     */
    public $maxValue = 10;
	
	/**
     * Font size.
     * @var int
     */
    public $fontSize = 14;
	
    /**
     * Avaliable values are 'jpeg' or 'png'
     * @var string 
     */
    public $imageFormat = self::PNG_FORMAT;

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
            $equation = $this->getVerifyCode(true);
            Yii::$app->response->format = Response::FORMAT_JSON;
            return [
                'hash1' => $this->generateValidationHash($equation),
                'hash2' => $this->generateValidationHash(strtolower($equation)),
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
    public function getVerifyCode($regenerate = false, $equation = false)
    {
        $session = Yii::$app->getSession();
        $session->open();
        $name = $this->getSessionKey();
        if ($session[$name] === null || $regenerate) {
            $session[$name . 'code'] = $this->generateVerifyCode();
            $session[$name] = $this->getValue($session[$name . 'code']);
            $session[$name . 'count'] = 1;
        }

        return $equation ? $session[$name . 'code'] : $session[$name];
    }

    /**
     * @inheritdoc
     */
    public function validate($input,$caseSensitive = false)
    {
        $equation = $this->getVerifyCode(false, true);
        $value = $this->getValue($equation);
        
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
		
		$equation = [
			'first' => mt_rand($this->minValue, $this->maxValue),
			'operator' => $this->operators[array_rand($this->operators)],
			'second' => mt_rand($this->minValue, $this->maxValue)
		];

        return $equation;
    }

    /**
     * @inheritdoc
     */
    protected function renderImage($equation)
    {
        require __DIR__ . '/mathpublisher.php';

        $formula = new \expression_math(tableau_expression(trim($this->getExpression($equation))));
        $formula->dessine($this->fontSize);

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
     * @param array $equation
     * @return string
     */
    protected function getExpression($equation)
    {
        if ($this->fixedVerifyCode !== null) {
            return $this->fixedVerifyCode;
        }
		
		return $equation['first'] ."~". $equation['operator'] ."~". $equation['second'] ."~=";
    }

    /**
     * Get value of formula
     * @param array $equation
     * @return int|float
     */
    protected function getValue($equation)
    {
        if ($this->fixedVerifyCode !== null) {
            return $this->fixedVerifyCode;
        }
		
		return eval('return '. $equation['first'] . $equation['operator'] . $equation['second'] .';');
    }
}
