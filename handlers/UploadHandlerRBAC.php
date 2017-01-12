<?php
use Yii;

namespace derekisbusy\filemanager\handlers;

class UploadHandlerRBAC extends BaseHandlerRBAC {
    /**
     * 
     * @param \derekisbusy\filemanager\events\FileManagerEvent $event
     */
    function begin()
    {
        if($this->event->controller->module->guestUpload) {
            return;
        }
        $this->checkIsGuest();
        if($this->canUpload()) {
            return;
        }
        if($this->canViewRelationInfo()) {
            return;
        }
        if($this->canViewOwnInfo() && $this->userId == Yii::$app->user->identity->id) {
            return;
        }
        $this->canViewOwner();
        throw new \yii\web\ForbiddenHttpException();
    }
    
}