<?php

namespace NetteStandard\Sniffs\Files;

use PHP_CodeSniffer_File;
use PHP_CodeSniffer_Sniff;

/**
 * Generic_Sniffs_Files_LineWarningSniffSniff.
 *
 * Checks all lines in the file, and throws warnings if they have whitespace(s) at end
 */
class SwitchCaseSpaceSniff implements PHP_CodeSniffer_Sniff
{

	/**
	 * Returns an array of tokens this test wants to listen for.
	 *
	 * @return array
	 */
	public function register()
	{
		return array(T_CASE);
	}

	/**
	 * Processes this test, when one of its tokens is encountered.
	 *
	 * @param PHP_CodeSniffer_File $phpcsFile The file being scanned.
	 * @param int                  $stackPtr  The position of the current token in
	 *                                        the stack passed in $tokens.
	 *
	 * @return int
	 */
	public function process(PHP_CodeSniffer_File $phpcsFile, $stackPtr)
	{
		$tokens = $phpcsFile->getTokens();
		for ($i = 1; $i < $phpcsFile->numTokens; $i++) {
			if ($tokens[$i]['column'] === 1) {
				$this->check($phpcsFile, $tokens, $i);
			}
		}

		$this->check($phpcsFile, $tokens, $i);

		// Ignore the rest of the file.
		return ($phpcsFile->numTokens + 1);
	}

	/**
	 * Checks if a line is too long.
	 *
	 * @param PHP_CodeSniffer_File $phpcsFile The file being scanned.
	 * @param array                $tokens    The token stack.
	 * @param int                  $stackPtr  The first token on the next line.
	 *
	 * @return null|void
	 */
	protected function check(PHP_CodeSniffer_File $phpcsFile, $tokens, $stackPtr)
	{
		// The passed token is the first on the line.
		$stackPtr--;

		if ($tokens[$stackPtr]['column'] !== 1 && $tokens[$stackPtr]['content'] === $phpcsFile->eolChar
		) {
			$stackPtr--;
		}

		if ($tokens[$stackPtr]['code'] === T_COLON && $tokens[$stackPtr - 1]['code'] === T_WHITESPACE) {
			$error = 'Found space(s) between colon and case value.';
			$fix = $phpcsFile->addFixableError($error, $stackPtr - 1, 'SpaceBeforeColon');

			if ($fix === true) {
				$prev = $phpcsFile->findPrevious(T_WHITESPACE, $stackPtr);
				for ($i = $stackPtr - 1; $i >= $prev; $i--) {
					$phpcsFile->fixer->replaceToken($i, trim($tokens[$i]['content'], " \t\0\x0B"));
				}
			}
		}
	}
}
