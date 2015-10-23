<?php
use Yii;

namespace derekisbusy\filemanager\handlers;

class InfoHandlerRBAC extends BaseHandlerRBAC {
    /**
     * 
     * @param \derekisbusy\filemanager\events\FileManagerEvent $event
     */
    function begin()
    {
        if($this->event->controller->module->guestDelete) {
            return;
        }
        $this->checkIsGuest();
        if($this->canDelete()) {
            return;
        }
        if($this->canDeleteRelation()) {
            return;
        }
        if($this->canDeleteOwn() && $this->userId == Yii::$app->user->identity->id) {
            return;
        }
        $this->canViewOwner();
        throw new \yii\web\ForbiddenHttpException();
    }
    
}