#!/bin/sh

# variables
VERSION="1.2.34_test_updateJirash"
IS_PLUGIN=TRUE
IS_CONNECTOR=FALSE
FULLNAME="JTL"
ABBREVIATION="JTL"

# check if the version tag is complete
php .gitlab/scripts/jiraReleaseVersion.php ${VERSION} ${IS_PLUGIN} ${IS_CONNECTOR} ${FULLNAME} ${ABBREVIATION}


