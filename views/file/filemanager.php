<?php

use derekisbusy\filemanager\assets\FilemanagerAsset;
use derekisbusy\filemanager\Module;
use yii\widgets\ListView;
use yii\helpers\Html;
use yii\helpers\Url;

/* @var $this yii\web\View */
/* @var $searchModel derekisbusy\filemanager\models\Mediafile */
/* @var $dataProvider yii\data\ActiveDataProvider */
$this->params['moduleBundle'] = FilemanagerAsset::register($this);
?>

<header id="header"><span class="glyphicon glyphicon-picture"></span> <?= Module::t('main', 'File manager') ?></header>

<div id="filemanager" data-url-info="<?= Url::to(['file/info']) ?>">
    <?= ListView::widget([
        'dataProvider' => $dataProvider,
        'layout' => '<div class="items">{items}</div>{pager}',
        'itemOptions' => ['class' => 'item'],
        'itemView' => function ($model, $key, $index, $widget) {
//        return $model->getDefaultThumbUrl($this->params['moduleBundle']->baseUrl);
//               if(file_exists(substr($model->getDefaultThumbUrl($this->params['moduleBundle']->baseUrl), 1))){
                    return Html::a(
                        Html::img($model->getDefaultThumbUrl($this->params['moduleBundle']->baseUrl))
                        . '<span class="checked glyphicon glyphicon-check"></span>',
                        '#mediafile',
                        ['data-key' => $key]
                    );
//                }
//                else{
//                    return 'not found?';
//                }
            },
    ]) ?>

    <div class="dashboard">
        <p><?= Html::a('<span class="glyphicon glyphicon-upload"></span> ' . Module::t('main', 'Upload manager'),
                ['file/uploadmanager','related'=>$related,'itemId'=>$itemId,'tempId'=>$tempId], ['class' => 'btn btn-default']) ?></p>
        <div id="fileinfo">

        </div>
    </div>
</div>