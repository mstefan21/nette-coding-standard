<?php

namespace NetteStandard\Sniffs\Commenting;

use PHP_CodeSniffer;
use PHP_CodeSniffer_File;
use Squiz_Sniffs_Commenting_VariableCommentSniff;

/**
 * Parses and verifies the variable doc comment.
 *
 * PHP version 5
 *
 * @category  PHP
 * @package   PHP_CodeSniffer
 * @author    Greg Sherwood <gsherwood@squiz.net>
 * @author    Marc McIntyre <mmcintyre@squiz.net>
 * @copyright 2006-2014 Squiz Pty Ltd (ABN 77 084 670 600)
 * @license   https://github.com/squizlabs/PHP_CodeSniffer/blob/master/licence.txt BSD Licence
 * @link      http://pear.php.net/package/PHP_CodeSniffer
 */
class VariableCommentSniff extends Squiz_Sniffs_Commenting_VariableCommentSniff
{

	/**
	 * Called to process class member vars.
	 *
	 * @param PHP_CodeSniffer_File $phpcsFile The file being scanned.
	 * @param int                  $stackPtr  The position of the current token
	 *                                        in the stack passed in $tokens.
	 *
	 * @return void
	 */
	public function processMemberVar(PHP_CodeSniffer_File $phpcsFile, $stackPtr)
	{
		$tokens = $phpcsFile->getTokens();
		$ignore = array(
			T_PUBLIC,
			T_PRIVATE,
			T_PROTECTED,
			T_VAR,
			T_STATIC,
			T_WHITESPACE,
		);

		$commentEnd = $phpcsFile->findPrevious($ignore, ($stackPtr - 1), null, true);
		if ($commentEnd === false || ($tokens[$commentEnd]['code'] !== T_DOC_COMMENT_CLOSE_TAG && $tokens[$commentEnd]['code'] !== T_COMMENT)
		) {
			$phpcsFile->addError('Missing member variable doc comment', $stackPtr, 'Missing');
			return;
		}

		if ($tokens[$commentEnd]['code'] === T_COMMENT) {
			$phpcsFile->addError('You must use "/**" style comments for a member variable comment', $stackPtr, 'WrongStyle');
			return;
		}

		$commentStart = $tokens[$commentEnd]['comment_opener'];

		$foundVar = null;
		foreach ($tokens[$commentStart]['comment_tags'] as $tag) {
			if ($tokens[$tag]['content'] === '@var') {
				if ($foundVar !== null) {
					$error = 'Only one @var tag is allowed in a member variable comment';
					$phpcsFile->addError($error, $tag, 'DuplicateVar');
				} else {
					$foundVar = $tag;
				}
			} else if ($tokens[$tag]['content'] === '@see') {
				// Make sure the tag isn't empty.
				$string = $phpcsFile->findNext(T_DOC_COMMENT_STRING, $tag, $commentEnd);
				if ($string === false || $tokens[$string]['line'] !== $tokens[$tag]['line']) {
					$error = 'Content missing for @see tag in member variable comment';
					$phpcsFile->addError($error, $tag, 'EmptySees');
				}
			} else {
				$error = '%s tag is not allowed in member variable comment';
				$data = array($tokens[$tag]['content']);
				$phpcsFile->addWarning($error, $tag, 'TagNotAllowed', $data);
			}//end if
		}//end foreach
		// The @var tag is the only one we require.
		if ($foundVar === null) {
			$error = 'Missing @var tag in member variable comment';
			$phpcsFile->addError($error, $commentEnd, 'MissingVar');
			return;
		}

		$firstTag = $tokens[$commentStart]['comment_tags'][0];
		if ($foundVar !== null && $tokens[$firstTag]['content'] !== '@var') {
			$error = 'The @var tag must be the first tag in a member variable comment';
			$phpcsFile->addError($error, $foundVar, 'VarOrder');
		}

		// Make sure the tag isn't empty and has the correct padding.
		$string = $phpcsFile->findNext(T_DOC_COMMENT_STRING, $foundVar, $commentEnd);
		if ($string === false || $tokens[$string]['line'] !== $tokens[$foundVar]['line']) {
			$error = 'Content missing for @var tag in member variable comment';
			$phpcsFile->addError($error, $foundVar, 'EmptyVar');
			return;
		}

		$varType = trim($tokens[($foundVar + 2)]['content']);
		$suggestedType = PHP_CodeSniffer::suggestType($varType);
		if (strtolower($varType) === 'type') {
			$error = 'Found not allowed type hint "type" in member variable comment';
			$phpcsFile->addError($error, ($foundVar + 2), 'IncorrectVarType');
		} elseif ($varType !== $suggestedType) {
			$error = 'Expected "%s" but found "%s" for @var tag in member variable comment';
			$data = array(
				$suggestedType,
				$varType,
			);

			$fix = $phpcsFile->addFixableError($error, ($foundVar + 2), 'IncorrectVarType', $data);
			if ($fix === true) {
				$phpcsFile->fixer->replaceToken(($foundVar + 2), $suggestedType);
			}
		}
	}
//end processMemberVar()

	/**
	 * Called to process a normal variable.
	 *
	 * Not required for this sniff.
	 *
	 * @param PHP_CodeSniffer_File $phpcsFile The PHP_CodeSniffer file where this token was found.
	 * @param int                  $stackPtr  The position where the double quoted
	 *                                        string was found.
	 *
	 * @return void
	 */
	protected function processVariable(PHP_CodeSniffer_File $phpcsFile, $stackPtr)
	{
		
	}
//end processVariable()

	/**
	 * Called to process variables found in double quoted strings.
	 *
	 * Not required for this sniff.
	 *
	 * @param PHP_CodeSniffer_File $phpcsFile The PHP_CodeSniffer file where this token was found.
	 * @param int                  $stackPtr  The position where the double quoted
	 *                                        string was found.
	 *
	 * @return void
	 */
	protected function processVariableInString(PHP_CodeSniffer_File $phpcsFile, $stackPtr)
	{
		
	}
//end processVariableInString()
}

//end class
