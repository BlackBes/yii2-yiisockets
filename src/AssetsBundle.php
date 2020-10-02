<?php
namespace blackbes\yiisockets;
use yii\web\AssetBundle;
class AssetsBundle extends AssetBundle
{
    public $sourcePath = '@vendor/blackbes/yii2-yiisockets/assets';
    public $publishOptions = ['forceCopy' => true];
    public $js = [
        'js/yiisockets-core.js'
    ];
    public $depends = [
        'yii\web\YiiAsset'
    ];
}