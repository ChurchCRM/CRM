local ApacheTestVer = "2.4";
local MeriadbTestVer = "10.3";

local CacheMountPath = "drone-ci";
local StepBuild = {
  name: "build",
  image: "devilbox/php-fpm:7.2-work",
  environment: {
    "FORWARD_PORTS_TO_LOCALHOST": "3306:mysql:3306",
    "PHP_MODULES_DISABLE": "xdebug",
  },
  commands: [
    "export DB=mysql",
    "php --version",
    "node --version",
    "composer --version",
    "apt-get update",
    "apt-get install -y ruby-full",
    "gem install sass -v 3.4.25",
    "chmod +x ./travis-ci/*.sh",
    "chmod +x ./scripts/*.sh",
    "cp BuildConfig.json.example BuildConfig.json",
    "chown -R devilbox:devilbox /drone/src/src",
    "npm install --unsafe-perm",
    "npm run composer-install",
    "npm run orm-gen",
  ],
};
local StoreCache = {
  name: "store-cache",
  image: "chrishsieh/drone-volume-cache",
  environment: {
    "PLUGIN_MOUNT": CacheMountPath,
    "PLUGIN_REBUILD": "true",
  },
  volumes: [ {
    name: "cache",
    path: "/cache",
  }],
};
local TestVersion(php_ver) = {
  name: "Test-" + php_ver,
  image: "devilbox/php-fpm:" + php_ver + "-work",
  environment: {
      "FORWARD_PORTS_TO_LOCALHOST": "3306:mysql:3306, 80:crm"+ php_ver + ":80",
      "PHP_MODULES_DISABLE": "xdebug",
    },
  commands: [
      "cp ./drone-ci/tests-run.sh ./scripts/tests-run.sh",
      "cp ./drone-ci/bootstrap.php ./tests/bootstrap.php",
      "npm run tests-install",
      "mysql --user=root --password=churchcrm --host=mysql -e 'drop database IF EXISTS churchcrm_test;'",
      "mysql --user=root --password=churchcrm --host=mysql -e 'create database IF NOT EXISTS churchcrm_test;'",
      "mysql --user=root --password=churchcrm --host=mysql churchcrm_test < src/mysql/install/Install.sql;",
      "mysql --user=root --password=churchcrm --host=mysql churchcrm_test < demo/ChurchCRM-Database.sql;",
      "sed -i 's/web_server/crm" + php_ver + "/g' ./drone-ci/Config.php",
      "sed -i 's/web_server/crm" + php_ver + "/g' ./drone-ci/behat.yml",
      "cp ./drone-ci/Config.php ./src/Include/Config.php",
      "cp ./drone-ci/behat.yml ./tests/behat/behat.yml",
      "npm run test",
    ],
};
local RestoreCache(php_ver) = {
  name: "restore-cache-" + php_ver,
  image: "chrishsieh/drone-volume-cache",
  environment: {
    "PLUGIN_MOUNT": CacheMountPath,
    "PLUGIN_RESTORE": "true",
  },
  volumes: [ {
    name: "cache",
    path: "/cache",
  }],
};
local ServiceDb(meriadb_ver) = {
  name: "mysql",
  image: "cytopia/mariadb-" + meriadb_ver,
  environment: {
    "MYSQL_ROOT_PASSWORD": "churchcrm",
  },
};
local ServicePhp(php_ver) = {
  name: "php" + php_ver,
  image: "devilbox/php-fpm:" + php_ver + "-work",
  environment: {
    "FORWARD_PORTS_TO_LOCALHOST": "3306:mysql:3306, 80:crm"+ php_ver + ":80",
    "PHP_MODULES_DISABLE": "xdebug",
  },
  commands: [
    "mkdir /var/www/default",
    "ln -s /drone/src/src/ /var/www/default/htdocs",
    "/docker-entrypoint.sh",
  ],
  working_dir: "/var/www/default"
};
local ServiceWeb(php_ver, apache_ver) = {
  name: "crm" + php_ver,
  image: "devilbox/apache-" + apache_ver,
  environment: {
    "PHP_FPM_ENABLE": 1,
    "PHP_FPM_SERVER_ADDR": "php" + php_ver,
    "PHP_FPM_SERVER_PORT": 9000,
    "MAIN_VHOST_ENABLE": 1,
    "MAIN_VHOST_SSL_CN": "crm" + php_ver,
  },
  commands: [
    "rm -rf /var/www/default/htdocs",
    "ln -s /drone/src/src/ /var/www/default/htdocs",
    "/docker-entrypoint.sh",
  ],
  working_dir: "/var/www/default"
};
local ServiceSelenium = {
  name: "selenium",
  image: "selenium/standalone-chrome",
  volumes: [
    {
      name: "shm",
      path: "/dev/shm:/dev/shm",
    }
  ],
};

local StartBuild = [ StepBuild, StoreCache, ];
local StartTestVer(php_ver) = {
  steps+: [ TestVersion(php_ver) , RestoreCache(php_ver), ],
  services+: [ ServicePhp(php_ver), ServiceWeb(php_ver, ApacheTestVer), ],
};

local PipeMain = 
{
  kind: "pipeline",
  name: "Build&Test",
  steps: StartBuild,
  services: [
    ServiceDb(MeriadbTestVer),
    ServiceSelenium,
  ],
  volumes: [{
    name: "cache",
    temp: {},
  }],
};

[ PipeMain + StartTestVer("7.0") + StartTestVer("7.1") + StartTestVer("7.2") ]
