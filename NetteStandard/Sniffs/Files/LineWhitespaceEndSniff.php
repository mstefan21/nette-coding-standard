<?php

namespace NetteStandard\Sniffs\Files;

use PHP_CodeSniffer_File;
use PHP_CodeSniffer_Sniff;

/**
 * Generic_Sniffs_Files_LineWarningSniffSniff.
 *
 * Checks all lines in the file, and throws warnings if they have whitespace(s) at end
 */
class LineWhitespaceEndSniff implements PHP_CodeSniffer_Sniff
{

	/**
	 * Returns an array of tokens this test wants to listen for.
	 *
	 * @return array
	 */
	public function register()
	{
		return array(T_OPEN_TAG);
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
				$this->checkWhitespaceEnd($phpcsFile, $tokens, $i);
			}
		}

		$this->checkWhitespaceEnd($phpcsFile, $tokens, $i);

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
	protected function checkWhitespaceEnd(PHP_CodeSniffer_File $phpcsFile, $tokens, $stackPtr)
	{
		// The passed token is the first on the line.
		$stackPtr--;

		if ($tokens[$stackPtr]['column'] === 1 && $tokens[$stackPtr]['length'] === 0
		) {
			// Blank line.
			return;
		}

		if ($tokens[$stackPtr]['column'] !== 1 && $tokens[$stackPtr]['content'] === $phpcsFile->eolChar
		) {
			$stackPtr--;
		}

		if ($tokens[$stackPtr]['code'] === T_WHITESPACE) {
			$error = 'Found whitespace(s) at end of line.';
			$fix = $phpcsFile->addFixableError($error, $stackPtr, 'InvalidEOLChar');

			if ($fix === true) {
				$phpcsFile->fixer->replaceToken($stackPtr, trim($tokens[$stackPtr]['content'], " \t\0\x0B"));
			}
		}
	}
}
