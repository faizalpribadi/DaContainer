# CONTRIBUTING

I' m always happy if somebody wants to help me out with this project and contribute to it.

But in order to keep this repo clean and sleek there are some guidelines:

## PSR

This project is following the PSR-0, PSR-1 and PSR-2 standards.

## Aditional coding conventions

The ```namespace``` declaration should go into the same line as the php opening tag:

    <?php namespace Foo;
    // ...

After control structures' (e.g. ```if```, ```foreach```) should be an empty line or a comment which explaines the structure.
There should be a blank line after the last statement within the control structure:

    if ($foo === true) {

      echo 'Bar';
    
    }
    
    // or...
    if ($foo === true) {
      //Comment or empty line
      echo 'Bar';
    
    }

## CI

This repos is tested with the CI server Travis-ci. A build can be skiped with the keyword ```[ci skip]```.

This keyword should go at the end of the main commit message line. It' s only allowed to be used if the commit changes static text/markdown files only.
If logic code is changed a build is necessary.


## Conclusion

If a pull request doesn' t full-fill these requirements I' ll kindly note it and give 168h (7 days) to fix it, if the violations has been fixed I' ll consire merging it.

Otherwise I' ll close it silently.
