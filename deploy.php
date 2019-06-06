<?php
namespace Deployer;

ini_set('include_path', ini_get('include_path') . PATH_SEPARATOR . 'vendor/deployer/recipes');

require 'recipe/common.php';


// Project name
set('application', 'APP NAME');

// Project repository
set('repository', 'GIT REPO');
set('branch', 'master');

// [Optional] Allocate tty for git clone. Default value is false.
set('git_tty', true);

// Shared files/dirs between deploys 
set('shared_files', []);
set('shared_dirs', ['tmp']);

// Writable dirs by web server 
set('writable_dirs', []);
set('allow_anonymous_stats', false);

set('composer_options', 'install --no-dev --ignore-platform-reqs --prefer-dist --no-interaction --optimize-autoloader');

// Hosts

host('production')
    ->hostname('IP TO HOST')
    ->stage('production')
    ->user('USER TO LOGIN AS')
    ->set('deploy_path', '/var/www/{{application}}/{{stage}}');

// Tasks

task('deploy:config', function () {
    // we can't use shared_files because how php and symlinks work
    run('cp {{deploy_path}}/shared/config/production.php {{release_path}}/config/production.php');
})->onStage('production');

desc('Compile di container');
task('di:optimize', function () {
    if (get('branch') === 'develop') {
        run('APP_ENV=staging {{release_path}}/vendor/bin/console di:compile');
    }
});

task('deploy:check_config', function () {
    $baseDir = __DIR__;
    if (runLocally('pwd') != $baseDir) {
        writeln('Can only be run in base directory: ' . $baseDir);
        return ;
    }

    $files = ['production.php'];

    cd('{{deploy_path}}/shared/');
    foreach ($files as $file) {
        try {
            $checkValue = run('sha512sum config/' . $file);
            file_put_contents($baseDir . '/config_checksum.sha512', $checkValue);
            if (testLocally('sha512sum --status -c ' . $baseDir . '/config_checksum.sha512')) {
                writeln("config: $file is up to date");
            } else {
                writeln("Upload new config: $file");
                upload($baseDir . '/config/' . $file, '{{deploy_path}}/shared/config/');
            }
            unlink($baseDir . '/config_checksum.sha512');
        } catch (\Exception $e) {
            writeln("Missing config: $file");
            upload($baseDir . '/config/' . $file, '{{deploy_path}}/shared/config/');
        }
    }
});

task('send_notification', function () {
    run('notify-send -i applications-development  "Deploy finished" "Finished deploing"');
})->local();

desc('Deploy your project');
task('deploy', [
    'deploy:info',
    'deploy:prepare',
    'deploy:lock',
    'deploy:release',
    'deploy:update_code',
    'deploy:check_config',
    'deploy:shared',
    'deploy:config',
    'deploy:writable',
    'deploy:vendors',
    'di:optimize',
    'deploy:clear_paths',
    'deploy:symlink',
    'deploy:unlock',
    'cleanup',
    'success'
]);


// [Optional] If deploy fails automatically unlock.
after('deploy:failed', 'deploy:unlock');

//show notification when done
after('deploy', 'send_notification');
