<?php

namespace NetteStandard\Sniffs\Classes;

use PHP_CodeSniffer_Sniff;
use PHP_CodeSniffer_File;
use PHP_CodeSniffer_Tokens;
use PEAR_Sniffs_Classes_ClassDeclarationSniff;

/**
 * Checks the declaration of the class and its inheritance is correct.
 *
 * (copied PSR2_Sniffs_Classes_ClassDeclarationSniff with small fixes)
 *
 * @author Greg Sherwood <gsherwood@squiz.net>
 * @author Jan Dolecek <juzna.cz@gmail.com>
 */
class ClassDeclarationSniff extends PEAR_Sniffs_Classes_ClassDeclarationSniff
{
    /** @var int */
    public $numBlankLinesBeforeClosingBrace = 0;


    /**
     * Processes this test, when one of its tokens is encountered.
     *
     * @param PHP_CodeSniffer_File $phpcsFile The file being scanned.
     * @param int                  $stackPtr  The position of the current token
     *                                         in the stack passed in $tokens.
     *
     * @return void
     */
    public function process(PHP_CodeSniffer_File $phpcsFile, $stackPtr)
    {
        // We want all the errors from the PEAR standard, plus some of our own.
        parent::process($phpcsFile, $stackPtr);
        $this->processOpen($phpcsFile, $stackPtr);
        $this->processClose($phpcsFile, $stackPtr);

    }//end process()


    /**
     * Processes the opening section of a class declaration.
     *
     * @param PHP_CodeSniffer_File $phpcsFile The file being scanned.
     * @param int                  $stackPtr  The position of the current token
     *                                        in the stack passed in $tokens.
     *
     * @return void
     */
    public function processOpen(PHP_CodeSniffer_File $phpcsFile, $stackPtr)
    {
        $tokens = $phpcsFile->getTokens();

        // Check alignment of the keyword and braces.
        if ($tokens[($stackPtr - 1)]['code'] === T_WHITESPACE) {
            $prevContent = $tokens[($stackPtr - 1)]['content'];
            if ($prevContent !== $phpcsFile->eolChar) {
                $blankSpace = substr($prevContent, strpos($prevContent, $phpcsFile->eolChar));
                $spaces     = strlen($blankSpace);

                if (in_array($tokens[($stackPtr - 2)]['code'], array(T_ABSTRACT, T_FINAL)) === true
                    && $spaces !== 1
                ) {
                    $type        = strtolower($tokens[$stackPtr]['content']);
                    $prevContent = strtolower($tokens[($stackPtr - 2)]['content']);
                    $error       = 'Expected 1 space between %s and %s keywords; %s found';
                    $data        = array(
                                    $prevContent,
                                    $type,
                                    $spaces,
                                   );
                    $phpcsFile->addError($error, $stackPtr, 'SpaceBeforeKeyword', $data);
                }
            }
        }//end if

        // We'll need the indent of the class/interface keyword for later.
        $classIndent = 0;
        if (strpos($tokens[($stackPtr - 1)]['content'], $phpcsFile->eolChar) === false) {
            $classIndent = strlen($tokens[($stackPtr - 1)]['content']);
        }

        $keyword      = $stackPtr;
        $openingBrace = $tokens[$stackPtr]['scope_opener'];
        $className    = $phpcsFile->findNext(T_STRING, $stackPtr);

        $classOrInterface = strtolower($tokens[$keyword]['content']);

        // Spacing of the keyword.
        $gap = $tokens[($stackPtr + 1)]['content'];
        if (strlen($gap) !== 1) {
            $found = strlen($gap);
            $error = 'Expected 1 space between %s keyword and %s name; %s found';
            $data  = array(
                      $classOrInterface,
                      $classOrInterface,
                      $found,
                     );
            $phpcsFile->addError($error, $stackPtr, 'SpaceAfterKeyword', $data);
        }

        // Check after the class/interface name.
        $gap = $tokens[($className + 1)]['content'];
        if (strlen($gap) !== 1) {
            $found = strlen($gap);
            $error = 'Expected 1 space after %s name; %s found';
            $data  = array(
                      $classOrInterface,
                      $found,
                     );
            $phpcsFile->addError($error, $stackPtr, 'SpaceAfterName', $data);
        }

        // Check positions of the extends and implements keywords.
        foreach (array('extends', 'implements') as $keywordType) {
            $keyword = $phpcsFile->findNext(constant('T_'.strtoupper($keywordType)), ($stackPtr + 1), $openingBrace);
            if ($keyword !== false) {
                if ($tokens[$keyword]['line'] !== $tokens[$stackPtr]['line']) {
                    $error = 'The '.$keywordType.' keyword must be on the same line as the %s name';
                    $data  = array($classOrInterface);
                    $phpcsFile->addError($error, $keyword, ucfirst($keywordType).'Line', $data);
                } else {
                    // Check the whitespace before. Whitespace after is checked
                    // later by looking at the whitespace before the first class name
                    // in the list.
                    $gap = strlen($tokens[($keyword - 1)]['content']);
                    if ($gap !== 1) {
                        $error = 'Expected 1 space before '.$keywordType.' keyword; %s found';
                        $data  = array($gap);
                        $phpcsFile->addError($error, $keyword, 'SpaceBefore'.ucfirst($keywordType), $data);
                    }
                }
            }
        }//end foreach

        // Check if extends/implements classes are at same line
        $classNames = array();
        $find       = array(T_STRING, T_EXTENDS, T_IMPLEMENTS);
        $nextClass  = $phpcsFile->findNext($find, ($className + 2), ($openingBrace - 1));
        while ($nextClass !== false) {
            $classNames[] = $nextClass;
            $nextClass    = $phpcsFile->findNext($find, ($nextClass + 1), ($openingBrace - 1));
        }
        $classCount = count($classNames);
        if ($classCount > 0) {
            if ($tokens[$className]['line'] !== $tokens[$classNames[$classCount - 1]]['line']) {
                $error = 'Extend and implement classes for "%s" class MUST be on same line.';
                $data = array(
                    $tokens[$className]['content'],
                );
                $phpcsFile->addError($error, $className, 'ExtendsInterfaceClassesOnSameLine', $data);
            }
        }

        // Check each of the extends/implements class names. If the implements
        // keywords is the last content on the line, it means we need to check for
        // the multi-line implements format, so we do not include the class names
        // from the implments list in the following check.
        $implements          = $phpcsFile->findNext(T_IMPLEMENTS, ($stackPtr + 1), $openingBrace);
        $multiLineImplements = false;
        if ($implements !== false) {
            $next = $phpcsFile->findNext(PHP_CodeSniffer_Tokens::$emptyTokens, ($implements + 1), $openingBrace, true);
            if ($tokens[$next]['line'] > $tokens[$implements]['line']) {
                $multiLineImplements = true;
            }
        }

        $classNames = array();
        $find       = array(T_STRING, T_IMPLEMENTS);
        $nextClass  = $phpcsFile->findNext($find, ($className + 2), ($openingBrace - 1));
        while ($nextClass !== false) {
            $classNames[] = $nextClass;
            $nextClass    = $phpcsFile->findNext($find, ($nextClass + 1), ($openingBrace - 1));
        }

        $classCount         = count($classNames);
        $checkingImplements = false;
        foreach ($classNames as $i => $className) {
            if ($tokens[$className]['code'] == T_IMPLEMENTS) {
                $checkingImplements = true;
                continue;
            }

            if ($checkingImplements === true
                && $multiLineImplements === true
                && ($tokens[($className - 1)]['code'] !== T_NS_SEPARATOR
                || $tokens[($className - 2)]['code'] !== T_STRING)
            ) {
                $prev = $phpcsFile->findPrevious(
                    array(T_NS_SEPARATOR, T_WHITESPACE),
                    ($className - 1),
                    $implements,
                    true
                );

                if ($tokens[$prev]['line'] !== ($tokens[$className]['line'] - 1)) {
                    $error = 'Only one interface may be specified per line in a multi-line implements declaration';
                    $phpcsFile->addError($error, $className, 'InterfaceSameLine');
                } else {
                    $prev     = $phpcsFile->findPrevious(T_WHITESPACE, ($className - 1), $implements);
                    $found    = strlen($tokens[$prev]['content']);
                    $expected = ($classIndent + $this->indent);
                    if ($found !== $expected) {
                        $error = 'Expected %s spaces before interface name; %s found';
                        $data  = array(
                                  $expected,
                                  $found
                                 );
                        $phpcsFile->addError($error, $className, 'InterfaceWrongIndent', $data);
                    }
                }
            } else if ($tokens[($className - 1)]['code'] !== T_NS_SEPARATOR
                || $tokens[($className - 2)]['code'] !== T_STRING
            ) {
                if ($tokens[($className - 1)]['code'] === T_COMMA
                    || ($tokens[($className - 1)]['code'] === T_NS_SEPARATOR
                    && $tokens[($className - 2)]['code'] === T_COMMA)
                ) {
                    $error = 'Expected 1 space before "%s"; 0 found';
                    $data  = array($tokens[$className]['content']);
                    $phpcsFile->addError($error, ($nextComma + 1), 'NoSpaceBeforeName', $data);
                } else {
                    if ($tokens[($className - 1)]['code'] === T_NS_SEPARATOR) {
                        $spaceBefore = strlen($tokens[($className - 2)]['content']);
                    } else {
                        $spaceBefore = strlen($tokens[($className - 1)]['content']);
                    }

                    if ($spaceBefore !== 1) {
                        $error = 'Expected 1 space before "%s"; %s found';
                        $data  = array(
                                  $tokens[$className]['content'],
                                  $spaceBefore,
                                 );
                        $phpcsFile->addError($error, $className, 'SpaceBeforeName', $data);
                    }
                }//end if
            }//end if

            if ($tokens[($className + 1)]['code'] !== T_NS_SEPARATOR
                && $tokens[($className + 1)]['code'] !== T_COMMA
            ) {
                if ($i !== ($classCount - 1)) {
                    // This is not the last class name, and the comma
                    // is not where we expect it to be.
                    if ($tokens[($className + 2)]['code'] !== T_IMPLEMENTS) {
                        $error = 'Expected 0 spaces between "%s" and comma; %s found';
                        $data  = array(
                                  $tokens[$className]['content'],
                                  strlen($tokens[($className + 1)]['content']),
                                 );
                        $phpcsFile->addError($error, $className, 'SpaceBeforeComma', $data);
                    }
                }

                $nextComma = $phpcsFile->findNext(T_COMMA, $className);
            } else {
                $nextComma = ($className + 1);
            }
        }//end foreach

    }//end processOpen()


    /**
     * Processes the closing section of a class declaration.
     *
     * @param PHP_CodeSniffer_File $phpcsFile The file being scanned.
     * @param int                  $stackPtr  The position of the current token
     *                                        in the stack passed in $tokens.
     *
     * @return void
     */
    public function processClose(PHP_CodeSniffer_File $phpcsFile, $stackPtr)
    {
        $tokens = $phpcsFile->getTokens();

        // Check that the closing brace comes right after the code body.
        $closeBrace = $tokens[$stackPtr]['scope_closer'];
        $prevContent = $phpcsFile->findPrevious(T_WHITESPACE, ($closeBrace - 1), null, true);
        if ($tokens[$prevContent]['line'] !== ($tokens[$closeBrace]['line'] - $this->numBlankLinesBeforeClosingBrace - 1)) {
            $error = 'The closing brace for the %s must go on the next line after the body';
            $data  = array($tokens[$stackPtr]['content']);
            $fix = $phpcsFile->addFixableError($error, $closeBrace, 'CloseBraceAfterBody', $data);

            if ($fix === TRUE) {
                $phpcsFile->fixer->replaceToken($closeBrace - 1, '');
            }
        }

        // Check the closing brace is on it's own line, but allow
        // for comments like "//end class".
        $nextContent = $phpcsFile->findNext(T_COMMENT, ($closeBrace + 1), null, true);
        if ($tokens[$nextContent]['content'] !== $phpcsFile->eolChar
            && $tokens[$nextContent]['line'] === $tokens[$closeBrace]['line']
        ) {
            $type  = strtolower($tokens[$stackPtr]['content']);
            $error = 'Closing %s brace must be on a line by itself';
            $data  = array($tokens[$stackPtr]['content']);
            $phpcsFile->addError($error, $closeBrace, 'CloseBraceSameLine', $data);
        }

    }//end processClose()


}//end class
