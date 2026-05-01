#!/bin/sh

faketty() {
    python3 -c "
import pty, os
status = pty.spawn(['/bin/bash', '-c', '$*'])
exit(os.waitstatus_to_exitcode(status))
"
}

for plugin in plugins/*; do
    cd $plugin
    npm test
    cd -
    faketty wp-env run tests-cli --env-cwd=wp-content/$plugin vendor/bin/phpunit --coverage-html coverage/php
done
