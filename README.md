# OS2forms EGIR integration [![Build Status](https://travis-ci.com/OS2Forms/os2forms_egir.svg?branch=develop)](https://travis-ci.org/OS2Forms/os2forms_egir)
Adds EGIR integration OS2forms med Forløb

## Installing OS2forms 2.1 med Forløb og EGIR integration
This module requires the codebase from the [OS2forms core project](https://github.com/OS2Forms/os2forms8) installed per the documentation and by selecting the os2forms_egir_profile at installation. After succesful installation you should have the OS2forms med Forløb and EGIR integration moduleavailable for install via gui.

You can also install the module by using Drush:
    ```
    ./vendor/bin/drush en os2forms_forloeb
    ```
    ```
    ./vendor/bin/drush en os2forms_egir
    ```

Add the following environmental variables to .env file in your installation (ideally by building the environment with Docker Compose):
    ```
    GIR_URL=http://localhost:5001
    ```
    ```
    GIR_EXTERNALS_ROOT=1e78e3a6-b999-2bff-981e-d46f5c37cce6    
    ```
