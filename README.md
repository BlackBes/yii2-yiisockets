Websockets for Yii2
===================
PHP Websockets with Yii2 integration based on Ratchet.

Installation
------------
he preferred way to install this extension is through [composer](http://getcomposer.org/download/).

 1) **Pull** this project in any directory. In this case it will be located in upper level folder

```bash
    cd ..
    git clone https://github.com/BlackBes/yii2-yiisockets.git 
```

 2) In your project's **composer.lock** add following lines:
```composer
    "require": {
        ...
        "blackbes/yii2-yiisockets": "@dev",
        ...
    },
    
    ...
    
    "repositories": [
        ...
        {
            "type": "path",
            "url": "../yii2-yiisockets"
        }
        ...
    ]
```

3) Run composer install in the project root
```bash
    composer install
```

4) Add following lines to your **config/console.php**.
**Note** that provided model should be the one you use to perform authorisation in your app. (Should implement Identity 
   Interface) 
```
    'components' => [
        ...
        'user' => [
            'class' => 'yii\web\User',
            'identityClass' => 'app\models\<YourModel>',
            'enableAutoLogin' => true
        ],
        ...
    ]
```

5) Add **SocketController.php** (you can find template [here]()) to your **commands** folder


6) Add **ecosystem.config.js** (you can find template [here]()) to your project **root** directory


7) Install [**npm**](https://linuxize.com/post/how-to-install-node-js-on-ubuntu-18.04/)


8) Install **pm2**
```bash
    npm i pm2 -g
```

Usage
-----

1) Create **sockets** folder in your root directory
2) Add controllers that extends **blackbes\yiisockets\BaseController**
3) Connect database
4) Start server
5) Start pm2
