Captcha With a Simple Math Equation
==========================

Installation
------------

The preferred way to install this extension is through [composer](http://getcomposer.org/download/).

Either run

```
php composer.phar require --prefer-dist hreitsma/yii2-simplemath-captcha "dev-master"
```

or add

```
"hreitsma/yii2-simplemath-captcha": "dev-master"
```

to the require section of your `composer.json` file.


Usage
-----

Once the extension is installed, simply modify your controler, add or change methode `actions()`:

```php
public function actions()
{
	return [
            ...
            'captcha' => [
                'class' => 'hr\captcha\CaptchaAction',
                'operators' => ['+','-','*'],
                'maxValue' => 10,
                'fontSize' => 18,
            ],
	];
}
```

In view
```php
<?php
use yii\captcha\Captcha;
?>

<?=
$form->field($model, 'verifyCode')->widget(Captcha::className(), [
    //'captchaAction' => 'site/captcha', // uncomment and change if your not in the default controller or a module
    'template' => '<div class="row"><div class="col-lg-2">{image}</div><div class="col-lg-10">{input}</div></div>',
])->hint('Hint: click on the equation to refresh')
?>
```

In config/web.php

```php
'rules' => [
    'site/captcha/<refresh:\d+>' => 'site/captcha',
    'site/captcha/<v:\w+>' => 'site/captcha',
]
```

![screenshot](http://s28.postimg.org/46fdggv0t/Captcha_example.jpg)
