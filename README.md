Websockets for Yii2
===================
PHP Websockets with Yii2 integration based on Ratchet.

Installation
------------
he preferred way to install this extension is through [composer](http://getcomposer.org/download/).

 1) **Clone** this project in any directory. In this case it will be located in upper level folder

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

Usage on back
-----

1) Create **sockets** folder in your root directory
   
2) Add controllers that extends **blackbes\yiisockets\BaseController**

4) Start server

5) Start pm2


BaseController Overview
-----

Extending this controller will provide following methods that you can use in your websocket controllers:

| Method                      | Parameters                                                                   | Returns                                                 | Example usage       |
| -------------               | -------------                                                                |-----------                                              |-----------          |
| send()                      | **ConnectionInterface** $conn, **string** $group_id, **mixed** data          | none                                                    |$this->send($this->conn, 'get-new-text', ['text' => $text]);
| sendError()                 | **ConnectionInterface** $conn, **string** $error_text                        | none                                                    | ---
| sendToGroupExcludeClient()  | **string** $action, **mixed** $data, **string** $group_id, **bool** $is_json | **boolean**                                             |$this->sendToGroup('new-message', ['text' => $text], 'chat-'.$chatId);
| sendToGroupExcludeUser()    | **string** $action, **mixed** $data, **string** $group_id, **bool** $is_json | **boolean**                                             |$this->sendToGroup('new-message', ['text' => $text], 'chat-'.$chatId);
| sendToGroup()               | **string** $action, **mixed** $data, **string** $group_id, **bool** $is_json | **boolean**                                             |$this->sendToGroup('new-message', ['text' => $text], 'chat-'.$chatId);
| addToGroup()                | **ConnectionInterface** $conn, **string** $group_id                          | **boolean**                                             |$this->addToGroup($this->conn,'chat-'.$chatId);
| removeFromGroup()           | **ConnectionInterface** $conn, **string** $group_id                          | **boolean**                                             |$this->removeFromGroup($this->conn,'chat-'.$chatId);
| isInGroup()                 | **ConnectionInterface** $conn, **string** $group_id                          | **boolean**                                             | ---
| GetGroup()                  | **string** $group_id                                                         | **mixed** - All connections from specific group         | ---
| getClientId()               | **ConnectionInterface** $conn                                                | **integer** - Id of user                                | ---
| getData()                   | **string** $data_name                                                        | **mixed** - Specified value that you've sent in request |$this->getData('chatId')

Connecting to websockets on front
-----

1) Add [**Yii2WebSockets**]() file to your JavaScript project and import it

   ```javascript
      import Yii2WebSockets from "path/to/yiisockets-core";
   ```

2) Create a variable object **login_credentials** with following properties:
   ```javascript
        let login_tokens = {
            'login-token': authToken, //Auth token that your identity uses to log in
            'connection-type': 'user'
        };
   ```

4) Start WebSocket connection
   ```javascript
       let _ws = new Yii2WebSockets(login_credentials);
   
       _ws.connect(socketAddress, socketPort, socketMode, socketRoute);
   
         // Default values are
         //   - socketAddress = 'localhost';
         //   - socketPort = '8088';
         //   - socketMode = 'ws';
         //   - socketRoute = '';
   ```

5) Add actions to listen to. Those will trigger when one of your contollers will use a sendToGroup() method which has same action name
   ```javascript
       let actionToListenTo = 'new-message' //this is for example
   
      _ws.addAction(action, function (data) {
           console.log(data) //here you decide what to do with data, when it arrives
       });
   ```
   Note that you can add multiple actions

Using websockets on front
-----
  1) Use socketSend method to make requests
      ```javascript
          let actionName = 'chat/send' // chat - controller name, send - controller's action (actionSend(){})
          let additionalData = {'text': "some example text"} // this property you can get in controller by using $this->getData('text')
          ws.socketSend(actionName, additionalData);
      ```
  2) Process responses in your addAction methods