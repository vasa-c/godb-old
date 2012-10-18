#!/bin/sh

if [ $# -eq 0 ]
then
    test_path="."
else
    test_path=$@
fi

phpunit --configuration "tests.xml" $test_path