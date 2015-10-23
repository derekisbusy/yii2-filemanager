<?php

namespace derekisbusy\filemanager\controllers;

use Yii;
use yii\web\Controller;
use yii\web\Response;
use yii\filters\VerbFilter;
use derekisbusy\filemanager\Module;
use derekisbusy\filemanager\models\Mediafile;
use derekisbusy\filemanager\assets\FilemanagerAsset;
use yii\helpers\Url;

class FileController extends Controller
{
    public $enableCsrfValidation = false;

    public function behaviors()
    {
        return array_merge([
            'verbs' => [
                'class' => VerbFilter::className(),
                'actions' => [
                    'delete' => ['post'],
                    'update' => ['post'],
                ],
            ]
        ], $this->module->controllerBehaviors);
    }

    public function beforeAction($action)
    {
        if (defined('YII_DEBUG') && YII_DEBUG) {
            Yii::$app->assetManager->forceCopy = true;
        }

        return parent::beforeAction($action);
    }

    public function actionIndex()
    {
        $event = new \derekisbusy\filemanager\events\IndexEvent();
        $this->module->trigger(Module::EVENT_INDEX, $event);
        return $this->render('index');
    }

    public function actionFilemanager($related=null, $itemId=null, $tempId=null)
    {
        $relation = null;
        if(isset($related)) {
            if(!isset($this->module->relations[$related])) {
                throw new \yii\base\InvalidParamException('Related model not defined in config');
            }
            $relation = $this->module->relations[$related];
        }
        $this->layout = '@vendor/derekisbusy/yii2-filemanager/views/layouts/main';
        $model = new Mediafile();
        $query = Mediafile::find()->orderBy('created_at DESC');
        
        if($tempId) {
            $query->where(['temp_id'=>$tempId]);
        }
        if($relation && $itemId) {
            $query->andWhere([$relation['model_id']=>$itemId]);
            $query->innerJoin($relation['class']::tableName(),"`{$relation['file_id']}`=`id`");
        }
        $event = new \derekisbusy\filemanager\events\FileManagerEvent();
        $this->module->trigger(Module::EVENT_FILE_MANAGER, $event);
        $dataProvider = $model->search($query);
        $dataProvider->pagination->defaultPageSize = $event->defaultPageSize ? $event->defaultPageSize : $this->module->defaultPageSize;
        
        $view = $event->view ? $event->view : Module::VIEW_FILE_MANAGER;
        return $this->render($view, [
            'model' => $model,
            'dataProvider' => $dataProvider,
            'related' => $related,
            'itemId' => $itemId,
            'tempId' => $tempId
        ]);
    }

    public function actionUploadmanager($related=null,$itemId=null,$tempId=null)
    {
        $event = new \derekisbusy\filemanager\events\UploadManagerEvent();
        $this->module->trigger(Module::EVENT_UPLOAD_MANAGER, $event);
        $this->layout = '@vendor/derekisbusy/yii2-filemanager/views/layouts/main';
        return $this->render('uploadmanager', ['model' => new Mediafile(),'related'=>$related,'itemId'=>$itemId,'tempId'=>$tempId]);
    }

    /**
     * Provides upload file
     * @return mixed
     */
    public function actionUpload($related=null,$itemId=null,$tempId=null)
    {
        $event = new \derekisbusy\filemanager\events\UploadEvent();
        $event->relatedClass = $this->module->relations[$related];
        $this->module->trigger(Module::EVENT_UPLOAD, $event);
        $relation=null;
        if(isset($related)) {
            if(!isset($this->module->relations[$related]))
                throw new \yii\base\InvalidParamException;
            $relation = $this->module->relations[$related];
        }
        $model = new Mediafile();
        $routes = $this->module->routes;
        $rename = $this->module->rename;
        if($tempId)
            $model->temp_id = $tempId;
        $model->saveUploadedFile($routes, $rename, $related, $itemId);
        // create relations
        if($related && $itemId) {
            $thru = new $this->module->relations[$related]['class']();
            $thru->{$relation['model_id']} = $itemId;
            $thru->{$relation['file_id']} = $model->id;
            $thru->save();
        }
        $bundle = FilemanagerAsset::register($this->view);

        if ($model->isImage()) {
            $model->createThumbs($routes, $this->module->thumbs);
        }
        Yii::$app->response->format = Response::FORMAT_JSON;
        $response=[];
        $response['files'][] = [
            'url'           => $model->url,
            'thumbnailUrl'  => $model->getDefaultThumbUrl($bundle->baseUrl),
            'name'          => $model->filename,
            'type'          => $model->type,
            'size'          => $model->file->size,
            'deleteUrl'     => Url::to(['file/delete', 'id' => $model->id]),
            'deleteType'    => 'POST',
        ];

        return $response;
    }

    /**
     * Updated mediafile by id
     * @param $id
     * @return array
     */
    public function actionUpdate($id)
    {
        $event = new \derekisbusy\filemanager\events\UpdateEvent();
        $this->module->trigger(Module::EVENT_UPDATE, $event);
        $model = Mediafile::findOne($id);
        $message = Module::t('main', 'Changes not saved.');

        if ($model->load(Yii::$app->request->post()) && $model->save()) {
            $message = Module::t('main', 'Changes saved!');
        }

        Yii::$app->session->setFlash('mediafileUpdateResult', $message);

        return $this->renderPartial('info', [
            'model' => $model,
            'strictThumb' => null,
        ]);
    }

    /**
     * Delete model with files
     * @param $id
     * @return array
     */
    public function actionDelete($id)
    {
        
        Yii::$app->response->format = Response::FORMAT_JSON;
        $routes = $this->module->routes;

        $model = Mediafile::findOne($id);
        
        $event = new \derekisbusy\filemanager\events\UploadManagerEvent();
        $this->module->trigger(Module::EVENT_DELETE, $event);

        if ($model->isImage()) {
            $model->deleteThumbs($routes);
        }

        $model->deleteFile($routes);
        $model->delete();

        return ['success' => 'true'];
    }

    /**
     * Resize all thumbnails
     */
    public function actionResize()
    {
        $event = new \derekisbusy\filemanager\events\ResizeEvent();
        $this->module->trigger(Module::EVENT_RESIZE, $event);
        
        $models = Mediafile::findByTypes(Mediafile::$imageFileTypes);
        
        $routes = $this->module->routes;

        foreach ($models as $model) {
            if ($model->isImage()) {
                $model->deleteThumbs($routes);
                $event = new \derekisbusy\filemanager\events\CreateThumbsEvent();
                $this->module->trigger(Module::EVENT_RESIZE, $event);
                $model->createThumbs($routes, $this->module->thumbs);
            }
        }

        Yii::$app->session->setFlash('successResize');
        $this->redirect(Url::to(['default/settings']));
    }

    /** Render model info
     * @param int $id
     * @param string $strictThumb only this thumb will be selected
     * @return string
     */
    public function actionInfo($id, $strictThumb = null)
    {
        $event = new \derekisbusy\filemanager\events\InfoEvent();
        $this->module->trigger(Module::EVENT_INFO, $event);
        $model = Mediafile::findOne($id);
        return $this->renderPartial('info', [
            'model' => $model,
            'strictThumb' => $strictThumb,
        ]);
    }
}
