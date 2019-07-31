yii2-upload-behavior
====================

File upload behavior for Yii2 ActiveRecord models.

[![Latest Stable Version](https://poser.pugx.org/Tigrov/yii2-upload-behavior/v/stable)](https://packagist.org/packages/Tigrov/yii2-upload-behavior)

Installation
------------

The preferred way to install this extension is through [composer](http://getcomposer.org/download/).

Either run

```
php composer.phar require --prefer-dist tigrov/yii2-upload-behavior
```

or add

```
"tigrov/yii2-upload-behavior": "~1.0"
```

to the require section of your `composer.json` file.

	
Usage
-----

Once the extension is installed, add the behavior to ActiveRecord model as follow:

Create a model with a file attribute
```php
class Model extends \yii\db\ActiveRecord
{
    /**
     * @inheritdoc
     */
    public function behaviors()
    {
        return [
            'upload' => [
                 'class' => '\tigrov\uploadBehavior\UploadBehavior',
                 'path' => '@runtime/upload',
                 'attributes' => ['file'],
            ],
        ];
    }
    
    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['file'], 'file', 'skipOnEmpty' => false],
        ];
    }
}
```

Create an action in a controller
```php
class FormController extends \yii\web\Controller
{
    public function actionUpload()
    {
        $model = new Model();
        if ($model->load(\Yii::$app->request->post()) && $model->save()) {
            \Yii::$app->getSession()->setFlash('success', 'Model is saved.');
            return $this->refresh();
        }

        return $this->render('form', [
            'model' => $model,
        ]);
    }
}
```

Create a form with the file attribute
```
<?php $form = ActiveForm::begin(); ?>
    <?= $form->field($model, 'file')->fileInput() ?>
    <?= Html::submitButton('Submit') ?>
<?php $form::end(); ?>
```

After submitting the file it will be saved to specified `path`.

License
-------

[MIT](LICENSE)
