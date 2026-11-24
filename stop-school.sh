#!/usr/bin/env bash
# Stops the SmartSchool started by start-school.sh.
pkill -f "artisan serve --host=127.0.0.1 --port=8000"
pkill -f "artisan schedule:work"
echo "SmartSchool stopped."
