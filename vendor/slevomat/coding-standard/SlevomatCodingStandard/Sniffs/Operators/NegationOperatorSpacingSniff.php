<?php declare(strict_types = 1);

namespace SlevomatCodingStandard\Sniffs\Operators;

use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;
use SlevomatCodingStandard\Helpers\FixerHelper;
use SlevomatCodingStandard\Helpers\IdentificatorHelper;
use SlevomatCodingStandard\Helpers\SniffSettingsHelper;
use SlevomatCodingStandard\Helpers\TokenHelper;
use function in_array;
use function sprintf;
use function strlen;
use const T_CLASS_C;
use const T_CLOSE_PARENTHESIS;
use const T_CLOSE_SHORT_ARRAY;
use const T_CLOSE_SQUARE_BRACKET;
use const T_CONSTANT_ENCAPSED_STRING;
use const T_DIR;
use const T_DNUMBER;
use const T_ENCAPSED_AND_WHITESPACE;
use const T_FILE;
use const T_FUNC_C;
use const T_LINE;
use const T_LNUMBER;
use const T_METHOD_C;
use const T_MINUS;
use const T_NS_C;
use const T_NUM_STRING;
use const T_TRAIT_C;
use const T_VARIABLE;
use const T_WHITESPACE;

class NegationOperatorSpacingSniff implements Sniff
{

	public const CODE_INVALID_SPACE_AFTER_MINUS = 'InvalidSpaceAfterMinus';

	public int $spacesCount = 0;

	/**
	 * @return array<int, (int|string)>
	 */
	public function register(): array
	{
		return [T_MINUS];
	}

	/**
	 * @phpcsSuppress SlevomatCodingStandard.TypeHints.ParameterTypeHint.MissingNativeTypeHint
	 * @param int $pointer
	 */
	public function process(File $phpcsFile, $pointer): void
	{
		$this->spacesCount = SniffSettingsHelper::normalizeInteger($this->spacesCount);

		$tokens = $phpcsFile->getTokens();

		$previousEffective = TokenHelper::findPreviousEffective($phpcsFile, $pointer - 1);

		$possibleOperandTypes = [
			...TokenHelper::ONLY_NAME_TOKEN_CODES,
			T_CONSTANT_ENCAPSED_STRING,
			T_CLASS_C,
			T_CLOSE_PARENTHESIS,
			T_CLOSE_SHORT_ARRAY,
			T_CLOSE_SQUARE_BRACKET,
			T_DIR,
			T_DNUMBER,
			T_ENCAPSED_AND_WHITESPACE,
			T_FILE,
			T_FUNC_C,
			T_LINE,
			T_LNUMBER,
			T_METHOD_C,
			T_NS_C,
			T_NUM_STRING,
			T_TRAIT_C,
			T_VARIABLE,
		];

		if (in_array($tokens[$previousEffective]['code'], $possibleOperandTypes, true)) {
			return;
		}

		$possibleVariableStartPointer = IdentificatorHelper::findStartPointer($phpcsFile, $previousEffective);
		if ($possibleVariableStartPointer !== null) {
			return;
		}

		$whitespacePointer = $pointer + 1;

		$numberOfSpaces = $tokens[$whitespacePointer]['code'] !== T_WHITESPACE ? 0 : strlen($tokens[$whitespacePointer]['content']);
		if ($numberOfSpaces === $this->spacesCount) {
			return;
		}

		$fix = $phpcsFile->addFixableError(
			sprintf(
				'Expected exactly %d space after "%s", %d found.',
				$this->spacesCount,
				$tokens[$pointer]['content'],
				$numberOfSpaces,
			),
			$pointer,
			self::CODE_INVALID_SPACE_AFTER_MINUS,
		);

		if (!$fix) {
			return;
		}

		if ($this->spacesCount > $numberOfSpaces) {
			FixerHelper::add($phpcsFile, $pointer, ' ');

			return;
		}

		FixerHelper::replace($phpcsFile, $whitespacePointer, '');
	}

}
