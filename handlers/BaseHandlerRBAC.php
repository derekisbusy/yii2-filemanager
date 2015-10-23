<?php

namespace derekisbusy\filemanager\handlers;


class BaseHandlerRBAC {
    
    protected function checkIsGuest()
    {
        if(Yii::$app->user->isGuest) {
            Yii::$event->controller->redirect(Yii::$app->user->loginUrl);
            Yii::$app->end();
        }
    }
    
    protected function canViewAll()
    {
        if(Yii::$app->user->can('filemanager-view-all')) {
            return true;
        } else {
            return false;
        }
    }
    
    protected function canViewRelation($relation)
    {
        if(!isset($this->event->controller->module->relations[$relation]))
            throw new \yii\base\InvalidRouteException();
        if(Yii::$app->user->can('filemanager-update-relation-'.$relation)) {
            return true;
        } else {
            return false;
        }
    }
    
    protected function canViewOwn()
    {
        if(Yii::$app->user->can('filemanager-view-own')) {
            if(!$this->userId) {
                $this->event->query->andWhere(['user_id' => Yii::$app->user->identity->id]);
            } elseif(!$this->isOwner()) {
                return false;
            }
            return true;
        }
        return false;
    }
    
    protected function canView()
    {
        $this->checkIsGuest();
        if(!Yii::$app->user->can('filemanager')) {
            return false;
        }
        if($this->canViewAll() || $this->canViewOwn()) {
            return true;
        }
        return false;
    }
    
    protected function isOwner()
    {
        return $this->userId == Yii::$app->user->identity->id;
    }
    
    protected function canUpload()
    {
        return Yii::$app->user->can('filemanager-upload');
    }
    
    protected function canUploadRelation($relation)
    {
        if(!isset($this->event->controller->module->relations[$relation]))
            throw new \yii\base\InvalidRouteException();
        if(Yii::$app->user->can('filemanager-relation-'.$relation)) {
            return true;
        } else {
            return false;
        }
    }
    
    
    protected function canUpdateRelation($relation)
    {
        if(!isset($this->event->controller->module->relations[$relation]))
            throw new \yii\base\InvalidRouteException();
        if(Yii::$app->user->can('filemanager-update-relation-'.$relation)) {
            return true;
        } else {
            return false;
        }
    }
    
    
    protected function canUpdate()
    {
        if(Yii::$app->user->can('filemanager-view-all')) {
            return true;
        } else {
            return false;
        }
    }
    
    protected function canUpdateOwn()
    {
        if(Yii::$app->user->can('filemanager-update-own')) {
            if(!$this->userId) {
                $this->event->query->andWhere(['user_id' => Yii::$app->user->identity->id]);
            } elseif(!$this->isOwner()) {
                return false;
            }
            return true;
        }
        return false;
    }
    
    protected function canDelete()
    {
        if(Yii::$app->user->can('filemanager-delete')) {
            return true;
        } else {
            return false;
        }
    }
    
    protected function canDeleteRelation($relation)
    {
        if(!isset($this->event->controller->module->relations[$relation]))
            throw new \yii\base\InvalidRouteException();
        if(Yii::$app->user->can('filemanager-delete-relation-'.$relation)) {
            return true;
        } else {
            return false;
        }
    }
    
    protected function canDeleteOwn()
    {
        if(Yii::$app->user->can('filemanager-delete-own')) {
            return true;
        } else {
            return false;
        }
    }
    
    protected function canAdmin()
    {
        if(Yii::$app->user->can('filemanager-admin')) {
            return true;
        } else {
            return false;
        }
    }
    
    protected function canViewOwnInfo()
    {
        if(Yii::$app->user->can('filemanager-view-info')) {
            return true;
        } else {
            return false;
        }
    }
    
    protected function canResizeAll()
    {
        if(Yii::$app->user->can('filemanager-resize-all')) {
            return true;
        } else {
            return false;
        }
    }
    
}