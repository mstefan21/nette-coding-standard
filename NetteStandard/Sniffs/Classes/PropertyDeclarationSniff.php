<?php

namespace NetteStandard\Sniffs\Classes;

use PHP_CodeSniffer_File;
use PHP_CodeSniffer_Standards_AbstractVariableSniff;
use PHP_CodeSniffer_Tokens;

/**
 * Verifies that properties are declared correctly.
 *
 * (copied PSR2_Sniffs_Classes_PropertyDeclarationSniff with small fixes)
 *
 * @author Greg Sherwood <gsherwood@squiz.net>
 * @author Jan Dolecek <juzna.cz@gmail.com>
 */
class PropertyDeclarationSniff extends PHP_CodeSniffer_Standards_AbstractVariableSniff
{


    /**
     * Processes the function tokens within the class.
     *
     * @param PHP_CodeSniffer_File $phpcsFile The file where this token was found.
     * @param int                  $stackPtr  The position where the token was found.
     *
     * @return void
     */
    protected function processMemberVar(PHP_CodeSniffer_File $phpcsFile, $stackPtr)
    {
        $tokens = $phpcsFile->getTokens();

        if ($tokens[$stackPtr]['content'][1] === '_') {
            $error = 'Property name "%s" should not be prefixed with an underscore to indicate visibility';
            $data  = array($tokens[$stackPtr]['content']);
            $phpcsFile->addWarning($error, $stackPtr, 'Underscore', $data);
        }

        // Detect multiple properties defined at the same time. Throw an error
        // for this, but also only process the first property in the list so we don't
        // repeat errors.
        $find = PHP_CodeSniffer_Tokens::$scopeModifiers;
        $find = array_merge($find, array(T_VARIABLE, T_VAR, T_SEMICOLON));
        $prev = $phpcsFile->findPrevious($find, ($stackPtr - 1));
        if ($tokens[$prev]['code'] === T_VARIABLE) {
            return;
        }

        if ($tokens[$prev]['code'] === T_VAR) {
            $error = 'The var keyword must not be used to declare a property';
            $phpcsFile->addError($error, $stackPtr, 'VarUsed');
        }

        $next = $phpcsFile->findNext(array(T_VARIABLE, T_SEMICOLON), ($stackPtr + 1));
        if ($tokens[$next]['code'] === T_VARIABLE) {
            $error = 'There must not be more than one property declared per statement';
            $phpcsFile->addError($error, $stackPtr, 'Multiple');
        }

        $modifier = $phpcsFile->findPrevious(PHP_CodeSniffer_Tokens::$scopeModifiers, $stackPtr);
        if (($modifier === false) || ($tokens[$modifier]['line'] !== $tokens[$stackPtr]['line'])) {
            $error = 'Visibility must be declared on property "%s". Public will be added in repair.';
            $data  = array($tokens[$stackPtr]['content']);
            $fix = $phpcsFile->addFixableError($error, $stackPtr, 'ScopeMissing', $data);
            if ($fix === TRUE) {
                if ($tokens[$stackPtr - 2]['code'] === T_STATIC) {
                    $phpcsFile->fixer->replaceToken($stackPtr - 2, 'public ' . $tokens[$stackPtr - 2]['content']);
                } else {
                    $phpcsFile->fixer->replaceToken($stackPtr, 'public ' . $tokens[$stackPtr]['content']);
                }
            }
        }

    }//end processMemberVar()


    /**
     * Processes normal variables.
     *
     * @param PHP_CodeSniffer_File $phpcsFile The file where this token was found.
     * @param int                  $stackPtr  The position where the token was found.
     *
     * @return void
     */
    protected function processVariable(PHP_CodeSniffer_File $phpcsFile, $stackPtr)
    {
        /*
            We don't care about normal variables.
        */

    }//end processVariable()


    /**
     * Processes variables in double quoted strings.
     *
     * @param PHP_CodeSniffer_File $phpcsFile The file where this token was found.
     * @param int                  $stackPtr  The position where the token was found.
     *
     * @return void
     */
    protected function processVariableInString(PHP_CodeSniffer_File $phpcsFile, $stackPtr)
    {
        /*
            We don't care about normal variables.
        */

    }//end processVariableInString()


}//end class
