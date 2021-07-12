module.exports = {
    apps : [{
        name: 'websocket',
        script: 'socket.sh',
        instances: 1,
        autorestart: true,
        watch: ["commands", "sockets", "yii2-yiisockets", "models"],
        ignore_watch: ["logs"],
        watch_options: {
            "followSymlinks": true
        },
        max_memory_restart: '1G',
        error_file: 'logs/error.log',
        out_file: 'logs/info.log',
        log_file: 'logs/combined.log',
    }],

    deploy : {
        production : {
            user : 'node',
            host : '212.83.163.1',
            ref  : 'origin/master',
            repo : 'git@github.com:repo.git',
            path : '/var/www/production',
            'post-deploy' : 'npm install && pm2 reload ecosystem.config.js --env production'
        }
    }
};
