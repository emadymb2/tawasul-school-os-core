# Install example data for TawasulOS

## Overview

This document provides instructions for installing example data on your Docker Desktop TawasulOS installation.

## Before you start

Make sure you have executed `./up.sh` from the repository root directory, and you can see TawasulOS with its INSTALLATION - STEP 1 page at http://localhost:8080.

## Steps

1. Run the `setup_devdb.sh` script to create the database and populate it with example data:
```bash
./setup_devdb.sh
```

If the script runs successfully, you should see the following output in the terminal:
```bash
$ ./setup_devdb.sh 
Cleaning up environment
OK: config.php deleted
OK: Recreated tawasul database
Generating config.php
OK: config.php created
Waiting for MySQL to accept connections...
OK: MySQL is ready
Executing tawasul.sql (this may take a few minutes)
OK: Imported schema
Executing tawasul_demo.sql
OK: Imported demo data
Creating admin user
OK: Created admin user
```

2. Go to http://localhost:8080 on your browser and log into TawasulOS with the following credentials:
* Username: **admin**
* Password: **Foobar888**

You will then see Staff Dashboard with example data.
