<?php

namespace NetteStandard\Sniffs\Commenting;

use PEAR_Sniffs_Commenting_FunctionCommentSniff;
use PHP_CodeSniffer;
use PHP_CodeSniffer_File;
use PHP_CodeSniffer_Tokens;

/**
 * Parses and verifies the doc comments for functions.
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
class FunctionCommentSniff extends PEAR_Sniffs_Commenting_FunctionCommentSniff
{

	/**
	 * The current PHP version.
	 *
	 * @var integer
	 */
	private $_phpVersion = null;

	/**
	 * Processes this test, when one of its tokens is encountered.
	 *
	 * @param PHP_CodeSniffer_File $phpcsFile The file being scanned.
	 * @param int                  $stackPtr  The position of the current token
	 *                                        in the stack passed in $tokens.
	 *
	 * @return void
	 */
	public function process(PHP_CodeSniffer_File $phpcsFile, $stackPtr)
	{
		$tokens = $phpcsFile->getTokens();
		$find = PHP_CodeSniffer_Tokens::$methodPrefixes;
		$find[] = T_WHITESPACE;

		$commentEnd = $phpcsFile->findPrevious($find, ($stackPtr - 1), null, true);
		if ($tokens[$commentEnd]['code'] === T_COMMENT) {
			// Inline comments might just be closing comments for
			// control structures or functions instead of function comments
			// using the wrong comment type. If there is other code on the line,
			// assume they relate to that code.
			$prev = $phpcsFile->findPrevious($find, ($commentEnd - 1), null, true);
			if ($prev !== false && $tokens[$prev]['line'] === $tokens[$commentEnd]['line']) {
				$commentEnd = $prev;
			}
		}

		if ($tokens[$commentEnd]['code'] !== T_DOC_COMMENT_CLOSE_TAG && $tokens[$commentEnd]['code'] !== T_COMMENT
		) {
			$phpcsFile->addError('Missing function doc comment', $stackPtr, 'Missing');
			$phpcsFile->recordMetric($stackPtr, 'Function has doc comment', 'no');
			return;
		} else {
			$phpcsFile->recordMetric($stackPtr, 'Function has doc comment', 'yes');
		}

		if ($tokens[$commentEnd]['code'] === T_COMMENT) {
			$phpcsFile->addError('You must use "/**" style comments for a function comment', $stackPtr, 'WrongStyle');
			return;
		}

		if ($tokens[$commentEnd]['line'] !== ($tokens[$stackPtr]['line'] - 1)) {
			$error = 'There must be no blank lines after the function comment';
			$phpcsFile->addError($error, $commentEnd, 'SpacingAfter');
		}

		$commentStart = $tokens[$commentEnd]['comment_opener'];
		foreach ($tokens[$commentStart]['comment_tags'] as $tag) {
			if ($tokens[$tag]['content'] === '@inheritDoc') {
				return;
			}

			if ($tokens[$tag]['content'] === '@see') {
				// Make sure the tag isn't empty.
				$string = $phpcsFile->findNext(T_DOC_COMMENT_STRING, $tag, $commentEnd);
				if ($string === false || $tokens[$string]['line'] !== $tokens[$tag]['line']) {
					$error = 'Content missing for @see tag in function comment';
					$phpcsFile->addError($error, $tag, 'EmptySees');
				}
			}
		}

		$this->processReturn($phpcsFile, $stackPtr, $commentStart);
		$this->processThrows($phpcsFile, $stackPtr, $commentStart);
		$this->processParams($phpcsFile, $stackPtr, $commentStart);
	}
//end process()

	/**
	 * Process the return comment of this function comment.
	 *
	 * @param PHP_CodeSniffer_File $phpcsFile    The file being scanned.
	 * @param int                  $stackPtr     The position of the current token
	 *                                           in the stack passed in $tokens.
	 * @param int                  $commentStart The position in the stack where the comment started.
	 *
	 * @return void
	 */
	protected function processReturn(PHP_CodeSniffer_File $phpcsFile, $stackPtr, $commentStart)
	{
		$tokens = $phpcsFile->getTokens();

		// Skip constructor and destructor.
		$methodName = $phpcsFile->getDeclarationName($stackPtr);
		$isSpecialMethod = ($methodName === '__construct' || $methodName === '__destruct');

		$return = null;
		foreach ($tokens[$commentStart]['comment_tags'] as $tag) {
			if ($tokens[$tag]['content'] === '@return') {
				if ($return !== null) {
					$error = 'Only 1 @return tag is allowed in a function comment';
					$phpcsFile->addError($error, $tag, 'DuplicateReturn');
					return;
				}

				$return = $tag;
			}
		}

		if ($isSpecialMethod === true) {
			return;
		}

		if ($return !== null) {
			$content = $tokens[($return + 2)]['content'];
			if (empty($content) === true || $tokens[($return + 2)]['code'] !== T_DOC_COMMENT_STRING) {
				$error = 'Return type missing for @return tag in function comment';
				$phpcsFile->addError($error, $return, 'MissingReturnType');
			} elseif (strtolower($content) === 'type') {
				$error = 'Found not allowed type hint "%s" for function return';
				$data = array(
					$content,
				);
				$phpcsFile->addError($error, $return, 'IncorrectVarType', $data);
			} else {
				// Check return type (can be multiple, separated by '|').
				$typeNames = explode('|', $content);
				$suggestedNames = array();
				foreach ($typeNames as $i => $typeName) {
					$suggestedName = PHP_CodeSniffer::suggestType($typeName);
					if (in_array($suggestedName, $suggestedNames) === false) {
						$suggestedNames[] = $suggestedName;
					}
				}

				$suggestedType = implode('|', $suggestedNames);
				if ($content !== $suggestedType) {
					$error = 'Expected "%s" but found "%s" for function return type';
					$data = array(
						$suggestedType,
						$content,
					);
					$fix = $phpcsFile->addError($error, $return, 'InvalidReturn', $data);
				}

				// Support both a return type and a description. The return type
				// is anything up to the first space.
				$returnParts = explode(' ', $content, 2);
				$returnType = $returnParts[0];

				// If the return type is void, make sure there is
				// no return statement in the function.
				if ($returnType === 'void') {
					if (isset($tokens[$stackPtr]['scope_closer']) === true) {
						$endToken = $tokens[$stackPtr]['scope_closer'];
						for ($returnToken = $stackPtr; $returnToken < $endToken; $returnToken++) {
							if ($tokens[$returnToken]['code'] === T_CLOSURE) {
								$returnToken = $tokens[$returnToken]['scope_closer'];
								continue;
							}

							if ($tokens[$returnToken]['code'] === T_RETURN || $tokens[$returnToken]['code'] === T_YIELD
							) {
								break;
							}
						}

						if ($returnToken !== $endToken) {
							// If the function is not returning anything, just
							// exiting, then there is no problem.
							$semicolon = $phpcsFile->findNext(T_WHITESPACE, ($returnToken + 1), null, true);
							if ($tokens[$semicolon]['code'] !== T_SEMICOLON) {
								$error = 'Function return type is void, but function contains return statement';
								$phpcsFile->addError($error, $return, 'InvalidReturnVoid');
							}
						}
					}//end if
				} else if ($returnType !== 'mixed') {
					// If return type is not void, there needs to be a return statement
					// somewhere in the function that returns something.
					if (isset($tokens[$stackPtr]['scope_closer']) === true) {
						$endToken = $tokens[$stackPtr]['scope_closer'];
						$returnToken = $phpcsFile->findNext(array(T_RETURN, T_YIELD), $stackPtr, $endToken);
						if ($returnToken === false) {
							$error = 'Function return type is not void, but function has no return statement';
							$phpcsFile->addError($error, $return, 'InvalidNoReturn');
						} else {
							$semicolon = $phpcsFile->findNext(T_WHITESPACE, ($returnToken + 1), null, true);
							if ($tokens[$semicolon]['code'] === T_SEMICOLON) {
								$error = 'Function return type is not void, but function is returning void here';
								$phpcsFile->addError($error, $returnToken, 'InvalidReturnNotVoid');
							}
						}
					}
				}//end if
			}//end if
		} else {
			$error = 'Missing @return tag in function comment';
			$phpcsFile->addError($error, $tokens[$commentStart]['comment_closer'], 'MissingReturn');
		}//end if
	}
//end processReturn()

	/**
	 * Process any throw tags that this function comment has.
	 *
	 * @param PHP_CodeSniffer_File $phpcsFile    The file being scanned.
	 * @param int                  $stackPtr     The position of the current token
	 *                                           in the stack passed in $tokens.
	 * @param int                  $commentStart The position in the stack where the comment started.
	 *
	 * @return void
	 */
	protected function processThrows(PHP_CodeSniffer_File $phpcsFile, $stackPtr, $commentStart)
	{
		$tokens = $phpcsFile->getTokens();

		$throws = array();
		foreach ($tokens[$commentStart]['comment_tags'] as $pos => $tag) {
			if ($tokens[$tag]['content'] !== '@throws') {
				continue;
			}

			$exception = null;
			$comment = null;
			if ($tokens[($tag + 2)]['code'] === T_DOC_COMMENT_STRING) {
				$matches = array();
				preg_match('/([^\s]+)(?:\s+(.*))?/', $tokens[($tag + 2)]['content'], $matches);
				$exception = $matches[1];
				if (isset($matches[2]) === true && trim($matches[2]) !== '') {
					$comment = $matches[2];
				}
			}

			if ($exception === null) {
				$error = 'Exception type and comment missing for @throws tag in function comment';
				$phpcsFile->addError($error, $tag, 'InvalidThrows');
			}
		}//end foreach
	}
//end processThrows()

	/**
	 * Process the function parameter comments.
	 *
	 * @param PHP_CodeSniffer_File $phpcsFile    The file being scanned.
	 * @param int                  $stackPtr     The position of the current token
	 *                                           in the stack passed in $tokens.
	 * @param int                  $commentStart The position in the stack where the comment started.
	 *
	 * @return void
	 */
	protected function processParams(PHP_CodeSniffer_File $phpcsFile, $stackPtr, $commentStart)
	{
		if ($this->_phpVersion === null) {
			$this->_phpVersion = PHP_CodeSniffer::getConfigData('php_version');
			if ($this->_phpVersion === null) {
				$this->_phpVersion = PHP_VERSION_ID;
			}
		}

		$tokens = $phpcsFile->getTokens();

		$params = array();
		$maxType = 0;
		$maxVar = 0;
		foreach ($tokens[$commentStart]['comment_tags'] as $pos => $tag) {
			if ($tokens[$tag]['content'] !== '@param') {
				continue;
			}

			$type = '';
			$typeSpace = 0;
			$var = '';
			$varSpace = 0;
			$comment = '';
			$commentLines = array();
			if ($tokens[($tag + 2)]['code'] === T_DOC_COMMENT_STRING) {
				$matches = array();
				preg_match('/([^$&.]+)(?:((?:\.\.\.)?(?:\$|&)[^\s]+)(?:(\s+)(.*))?)?/', $tokens[($tag + 2)]['content'], $matches);

				if (empty($matches) === false) {
					$typeLen = strlen($matches[1]);
					$type = trim($matches[1]);
					$typeSpace = ($typeLen - strlen($type));
					$typeLen = strlen($type);
					if ($typeLen > $maxType) {
						$maxType = $typeLen;
					}
				}

				if (isset($matches[2]) === true) {
					$var = $matches[2];
					$varLen = strlen($var);
					if ($varLen > $maxVar) {
						$maxVar = $varLen;
					}

					if (isset($matches[4]) === true) {
						$varSpace = strlen($matches[3]);
						$comment = $matches[4];
						$commentLines[] = array(
							'comment' => $comment,
							'token' => ($tag + 2),
							'indent' => $varSpace,
						);

						// Any strings until the next tag belong to this comment.
						if (isset($tokens[$commentStart]['comment_tags'][($pos + 1)]) === true) {
							$end = $tokens[$commentStart]['comment_tags'][($pos + 1)];
						} else {
							$end = $tokens[$commentStart]['comment_closer'];
						}

						for ($i = ($tag + 3); $i < $end; $i++) {
							if ($tokens[$i]['code'] === T_DOC_COMMENT_STRING) {
								$indent = 0;
								if ($tokens[($i - 1)]['code'] === T_DOC_COMMENT_WHITESPACE) {
									$indent = strlen($tokens[($i - 1)]['content']);
								}

								$comment .= ' ' . $tokens[$i]['content'];
								$commentLines[] = array(
									'comment' => $tokens[$i]['content'],
									'token' => $i,
									'indent' => $indent,
								);
							}
						}
					} else {
						$error = 'Missing parameter comment';
						$phpcsFile->addError($error, $tag, 'MissingParamComment');
						$commentLines[] = array('comment' => '');
					}//end if
				} else {
					$error = 'Missing parameter name';
					$phpcsFile->addError($error, $tag, 'MissingParamName');
				}//end if
			} else {
				$error = 'Missing parameter type';
				$phpcsFile->addError($error, $tag, 'MissingParamType');
			}//end if

			$params[] = array(
				'tag' => $tag,
				'type' => $type,
				'var' => $var,
				'comment' => $comment,
				'commentLines' => $commentLines,
				'type_space' => $typeSpace,
				'var_space' => $varSpace,
			);
		}//end foreach

		$realParams = $phpcsFile->getMethodParameters($stackPtr);
		$foundParams = array();

		// We want to use ... for all variable length arguments, so added
		// this prefix to the variable name so comparisons are easier.
		foreach ($realParams as $pos => $param) {
			if ($param['variable_length'] === true) {
				$realParams[$pos]['name'] = '...' . $realParams[$pos]['name'];
			}
		}

		foreach ($params as $pos => $param) {
			// If the type is empty, the whole line is empty.
			if ($param['type'] === '') {
				continue;
			}

			// Check the param type value.
			$typeNames = explode('|', $param['type']);
			foreach ($typeNames as $typeName) {
				$suggestedName = PHP_CodeSniffer::suggestType($typeName);
				if ($typeName !== $suggestedName) {
					$error = 'Expected "%s" but found "%s" for parameter type';
					$data = array(
						$suggestedName,
						$typeName,
					);

					$phpcsFile->addError($error, $param['tag'], 'IncorrectParamVarName', $data);
				} else if (count($typeNames) === 1) {
					// Check type hint for array and custom type.
					$suggestedTypeHint = '';
					if (strpos($suggestedName, 'array') !== false || substr($suggestedName, -2) === '[]') {
						$suggestedTypeHint = 'array';
					} else if (strpos($suggestedName, 'callable') !== false) {
						$suggestedTypeHint = 'callable';
					} else if (strpos($suggestedName, 'callback') !== false) {
						$suggestedTypeHint = 'callable';
					} else if (in_array($typeName, PHP_CodeSniffer::$allowedTypes) === false) {
						$suggestedTypeHint = $suggestedName;
					} else if ($this->_phpVersion >= 70000) {
						if ($typeName === 'string') {
							$suggestedTypeHint = 'string';
						} else if ($typeName === 'int' || $typeName === 'integer') {
							$suggestedTypeHint = 'int';
						} else if ($typeName === 'float') {
							$suggestedTypeHint = 'float';
						} else if ($typeName === 'bool' || $typeName === 'boolean') {
							$suggestedTypeHint = 'bool';
						}
					}

					if ($suggestedTypeHint !== '' && isset($realParams[$pos]) === true) {
						$typeHint = $realParams[$pos]['type_hint'];
						if ($typeHint === '') {
							$error = 'Type hint "%s" missing for %s';
							$data = array(
								$suggestedTypeHint,
								$param['var'],
							);

							$errorCode = 'TypeHintMissing';
							if ($suggestedTypeHint === 'string' || $suggestedTypeHint === 'int' || $suggestedTypeHint === 'float' || $suggestedTypeHint === 'bool'
							) {
								$errorCode = 'Scalar' . $errorCode;
							}

							$phpcsFile->addError($error, $stackPtr, $errorCode, $data);
						} else if ($typeHint !== substr($suggestedTypeHint, (strlen($typeHint) * -1))) {
							$error = 'Expected type hint "%s"; found "%s" for %s';
							$data = array(
								$suggestedTypeHint,
								$typeHint,
								$param['var'],
							);
							$phpcsFile->addError($error, $stackPtr, 'IncorrectTypeHint', $data);
						}//end if
					} else if ($suggestedTypeHint === '' && isset($realParams[$pos]) === true) {
						$typeHint = $realParams[$pos]['type_hint'];
						if ($typeHint !== '') {
							$error = 'Unknown type hint "%s" found for %s';
							$data = array(
								$typeHint,
								$param['var'],
							);
							$phpcsFile->addError($error, $stackPtr, 'InvalidTypeHint', $data);
						}
					}//end if
				}//end if
			}//end foreach

			if ($param['var'] === '') {
				continue;
			}

			$foundParams[] = $param['var'];

			// Make sure the param name is correct.
			if (isset($realParams[$pos]) === true) {
				$realName = $realParams[$pos]['name'];
				if ($realName !== $param['var']) {
					$code = 'ParamNameNoMatch';
					$data = array(
						$param['var'],
						$realName,
					);

					$error = 'Doc comment for parameter %s does not match ';
					if (strtolower($param['var']) === strtolower($realName)) {
						$error .= 'case of ';
						$code = 'ParamNameNoCaseMatch';
					}

					$error .= 'actual variable name %s';

					$phpcsFile->addError($error, $param['tag'], $code, $data);
				}
			} else if (substr($param['var'], -4) !== ',...') {
				// We must have an extra parameter comment.
				$error = 'Superfluous parameter comment';
				$phpcsFile->addError($error, $param['tag'], 'ExtraParamComment');
			}//end if

			if ($param['comment'] === '') {
				continue;
			}

			// Param comments must start with a capital letter and end with the full stop.
			if (preg_match('/^(\[OPTIONAL\])/u', $param['comment']) === 0 && preg_match('/^(\p{Ll}|\P{L})/u', $param['comment']) === 1) {
				$error = 'Parameter comment must start with a capital letter';
				$phpcsFile->addError($error, $param['tag'], 'ParamCommentNotCapital');
			}
		}//end foreach

		$realNames = array();
		foreach ($realParams as $realParam) {
			$realNames[] = $realParam['name'];
		}

		// Report missing comments.
		$diff = array_diff($realNames, $foundParams);
		foreach ($diff as $neededParam) {
			$error = 'Missing @param tag in function comment for "%s" parameter';
			$data = array($neededParam);
			$phpcsFile->addError($error, $commentStart, 'MissingParamTag', $data);
		}
	}
//end processParams()
}

//end class
