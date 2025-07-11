<?php declare(strict_types = 1);

namespace SlevomatCodingStandard\Sniffs\Classes;

use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;
use PHP_CodeSniffer\Util\Tokens;
use SlevomatCodingStandard\Helpers\AnnotationHelper;
use SlevomatCodingStandard\Helpers\AttributeHelper;
use SlevomatCodingStandard\Helpers\ClassHelper;
use SlevomatCodingStandard\Helpers\DocCommentHelper;
use SlevomatCodingStandard\Helpers\FixerHelper;
use SlevomatCodingStandard\Helpers\FunctionHelper;
use SlevomatCodingStandard\Helpers\NamespaceHelper;
use SlevomatCodingStandard\Helpers\PropertyHelper;
use SlevomatCodingStandard\Helpers\SniffSettingsHelper;
use SlevomatCodingStandard\Helpers\StringHelper;
use SlevomatCodingStandard\Helpers\TokenHelper;
use function array_diff;
use function array_filter;
use function array_flip;
use function array_key_exists;
use function array_keys;
use function array_merge;
use function array_shift;
use function array_values;
use function assert;
use function implode;
use function in_array;
use function ltrim;
use function preg_replace;
use function preg_split;
use function sprintf;
use function str_repeat;
use function strtolower;
use function substr;
use const PREG_SPLIT_NO_EMPTY;
use const T_ABSTRACT;
use const T_ATTRIBUTE_END;
use const T_CLOSE_CURLY_BRACKET;
use const T_CONST;
use const T_ENUM_CASE;
use const T_FINAL;
use const T_FUNCTION;
use const T_OPEN_CURLY_BRACKET;
use const T_PRIVATE_SET;
use const T_PROTECTED;
use const T_PROTECTED_SET;
use const T_PUBLIC;
use const T_PUBLIC_SET;
use const T_SEMICOLON;
use const T_STATIC;
use const T_USE;
use const T_VARIABLE;
use const T_WHITESPACE;

class ClassStructureSniff implements Sniff
{

	public const CODE_INCORRECT_GROUP_ORDER = 'IncorrectGroupOrder';

	private const GROUP_USES = 'uses';
	private const GROUP_PUBLIC_CONSTANTS = 'public constants';
	private const GROUP_PROTECTED_CONSTANTS = 'protected constants';
	private const GROUP_PRIVATE_CONSTANTS = 'private constants';
	private const GROUP_PUBLIC_PROPERTIES = 'public properties';
	private const GROUP_PUBLIC_STATIC_PROPERTIES = 'public static properties';
	private const GROUP_PROTECTED_PROPERTIES = 'protected properties';
	private const GROUP_PROTECTED_STATIC_PROPERTIES = 'protected static properties';
	private const GROUP_PRIVATE_PROPERTIES = 'private properties';
	private const GROUP_PRIVATE_STATIC_PROPERTIES = 'private static properties';
	private const GROUP_CONSTRUCTOR = 'constructor';
	private const GROUP_STATIC_CONSTRUCTORS = 'static constructors';
	private const GROUP_DESTRUCTOR = 'destructor';
	private const GROUP_INVOKE_METHOD = 'invoke method';
	private const GROUP_MAGIC_METHODS = 'magic methods';
	private const GROUP_PUBLIC_METHODS = 'public methods';
	private const GROUP_PUBLIC_ABSTRACT_METHODS = 'public abstract methods';
	private const GROUP_PUBLIC_FINAL_METHODS = 'public final methods';
	private const GROUP_PUBLIC_STATIC_METHODS = 'public static methods';
	private const GROUP_PUBLIC_STATIC_ABSTRACT_METHODS = 'public static abstract methods';
	private const GROUP_PUBLIC_STATIC_FINAL_METHODS = 'public static final methods';
	private const GROUP_PROTECTED_METHODS = 'protected methods';
	private const GROUP_PROTECTED_ABSTRACT_METHODS = 'protected abstract methods';
	private const GROUP_PROTECTED_FINAL_METHODS = 'protected final methods';
	private const GROUP_PROTECTED_STATIC_METHODS = 'protected static methods';
	private const GROUP_PROTECTED_STATIC_ABSTRACT_METHODS = 'protected static abstract methods';
	private const GROUP_PROTECTED_STATIC_FINAL_METHODS = 'protected static final methods';
	private const GROUP_PRIVATE_METHODS = 'private methods';
	private const GROUP_PRIVATE_STATIC_METHODS = 'private static methods';
	private const GROUP_ENUM_CASES = 'enum cases';

	private const GROUP_SHORTCUT_CONSTANTS = 'constants';
	private const GROUP_SHORTCUT_PROPERTIES = 'properties';
	private const GROUP_SHORTCUT_STATIC_PROPERTIES = 'static properties';
	private const GROUP_SHORTCUT_METHODS = 'methods';
	private const GROUP_SHORTCUT_PUBLIC_METHODS = 'all public methods';
	private const GROUP_SHORTCUT_PROTECTED_METHODS = 'all protected methods';
	private const GROUP_SHORTCUT_PRIVATE_METHODS = 'all private methods';
	private const GROUP_SHORTCUT_STATIC_METHODS = 'static methods';
	private const GROUP_SHORTCUT_ABSTRACT_METHODS = 'abstract methods';
	private const GROUP_SHORTCUT_FINAL_METHODS = 'final methods';

	private const SHORTCUTS = [
		self::GROUP_SHORTCUT_CONSTANTS => [
			self::GROUP_PUBLIC_CONSTANTS,
			self::GROUP_PROTECTED_CONSTANTS,
			self::GROUP_PRIVATE_CONSTANTS,
		],
		self::GROUP_SHORTCUT_STATIC_PROPERTIES => [
			self::GROUP_PUBLIC_STATIC_PROPERTIES,
			self::GROUP_PROTECTED_STATIC_PROPERTIES,
			self::GROUP_PRIVATE_STATIC_PROPERTIES,
		],
		self::GROUP_SHORTCUT_PROPERTIES => [
			self::GROUP_SHORTCUT_STATIC_PROPERTIES,
			self::GROUP_PUBLIC_PROPERTIES,
			self::GROUP_PROTECTED_PROPERTIES,
			self::GROUP_PRIVATE_PROPERTIES,
		],
		self::GROUP_SHORTCUT_PUBLIC_METHODS => [
			self::GROUP_PUBLIC_FINAL_METHODS,
			self::GROUP_PUBLIC_STATIC_FINAL_METHODS,
			self::GROUP_PUBLIC_ABSTRACT_METHODS,
			self::GROUP_PUBLIC_STATIC_ABSTRACT_METHODS,
			self::GROUP_PUBLIC_STATIC_METHODS,
			self::GROUP_PUBLIC_METHODS,
		],
		self::GROUP_SHORTCUT_PROTECTED_METHODS => [
			self::GROUP_PROTECTED_FINAL_METHODS,
			self::GROUP_PROTECTED_STATIC_FINAL_METHODS,
			self::GROUP_PROTECTED_ABSTRACT_METHODS,
			self::GROUP_PROTECTED_STATIC_ABSTRACT_METHODS,
			self::GROUP_PROTECTED_STATIC_METHODS,
			self::GROUP_PROTECTED_METHODS,
		],
		self::GROUP_SHORTCUT_PRIVATE_METHODS => [
			self::GROUP_PRIVATE_STATIC_METHODS,
			self::GROUP_PRIVATE_METHODS,
		],
		self::GROUP_SHORTCUT_FINAL_METHODS => [
			self::GROUP_PUBLIC_FINAL_METHODS,
			self::GROUP_PROTECTED_FINAL_METHODS,
			self::GROUP_PUBLIC_STATIC_FINAL_METHODS,
			self::GROUP_PROTECTED_STATIC_FINAL_METHODS,
		],
		self::GROUP_SHORTCUT_ABSTRACT_METHODS => [
			self::GROUP_PUBLIC_ABSTRACT_METHODS,
			self::GROUP_PROTECTED_ABSTRACT_METHODS,
			self::GROUP_PUBLIC_STATIC_ABSTRACT_METHODS,
			self::GROUP_PROTECTED_STATIC_ABSTRACT_METHODS,
		],
		self::GROUP_SHORTCUT_STATIC_METHODS => [
			self::GROUP_STATIC_CONSTRUCTORS,
			self::GROUP_PUBLIC_STATIC_FINAL_METHODS,
			self::GROUP_PROTECTED_STATIC_FINAL_METHODS,
			self::GROUP_PUBLIC_STATIC_ABSTRACT_METHODS,
			self::GROUP_PROTECTED_STATIC_ABSTRACT_METHODS,
			self::GROUP_PUBLIC_STATIC_METHODS,
			self::GROUP_PROTECTED_STATIC_METHODS,
			self::GROUP_PRIVATE_STATIC_METHODS,
		],
		self::GROUP_SHORTCUT_METHODS => [
			self::GROUP_SHORTCUT_FINAL_METHODS,
			self::GROUP_SHORTCUT_ABSTRACT_METHODS,
			self::GROUP_SHORTCUT_STATIC_METHODS,
			self::GROUP_CONSTRUCTOR,
			self::GROUP_DESTRUCTOR,
			self::GROUP_PUBLIC_METHODS,
			self::GROUP_PROTECTED_METHODS,
			self::GROUP_PRIVATE_METHODS,
			self::GROUP_MAGIC_METHODS,
		],
	];

	private const SPECIAL_METHODS = [
		'__construct' => self::GROUP_CONSTRUCTOR,
		'__destruct' => self::GROUP_DESTRUCTOR,
		'__call' => self::GROUP_MAGIC_METHODS,
		'__callstatic' => self::GROUP_MAGIC_METHODS,
		'__get' => self::GROUP_MAGIC_METHODS,
		'__set' => self::GROUP_MAGIC_METHODS,
		'__isset' => self::GROUP_MAGIC_METHODS,
		'__unset' => self::GROUP_MAGIC_METHODS,
		'__sleep' => self::GROUP_MAGIC_METHODS,
		'__wakeup' => self::GROUP_MAGIC_METHODS,
		'__serialize' => self::GROUP_MAGIC_METHODS,
		'__unserialize' => self::GROUP_MAGIC_METHODS,
		'__tostring' => self::GROUP_MAGIC_METHODS,
		'__invoke' => self::GROUP_INVOKE_METHOD,
		'__set_state' => self::GROUP_MAGIC_METHODS,
		'__clone' => self::GROUP_MAGIC_METHODS,
		'__debuginfo' => self::GROUP_MAGIC_METHODS,
	];

	/** @var array<string, string> */
	public array $methodGroups = [];

	/** @var list<string> */
	public array $groups = [];

	/** @var array<string, list<array{name: string|null, attributes: array<string>, annotations: array<string>}>>|null */
	private ?array $normalizedMethodGroups = null;

	/** @var array<string, int>|null */
	private ?array $normalizedGroups = null;

	/**
	 * @return array<int, (int|string)>
	 */
	public function register(): array
	{
		return array_values(Tokens::$ooScopeTokens);
	}

	/**
	 * @phpcsSuppress SlevomatCodingStandard.TypeHints.ParameterTypeHint.MissingNativeTypeHint
	 * @param int $pointer
	 */
	public function process(File $phpcsFile, $pointer): int
	{
		$tokens = $phpcsFile->getTokens();
		$rootScopeToken = $tokens[$pointer];
		assert(array_key_exists('scope_opener', $rootScopeToken));

		$groupsOrder = $this->getNormalizedGroups();

		$groupLastMemberPointer = $rootScopeToken['scope_opener'];
		$expectedGroup = null;
		$groupsFirstMembers = [];
		while (true) {
			$nextGroup = $this->findNextGroup($phpcsFile, $groupLastMemberPointer, $rootScopeToken);
			if ($nextGroup === null) {
				break;
			}

			[$groupFirstMemberPointer, $groupLastMemberPointer, $group] = $nextGroup;

			// Use "magic methods" group for __invoke() when "invoke" group is not explicitly defined
			if ($group === self::GROUP_INVOKE_METHOD && !array_key_exists($group, $groupsOrder)) {
				$group = self::GROUP_MAGIC_METHODS;
			}

			if ($groupsOrder[$group] >= ($groupsOrder[$expectedGroup] ?? 0)) {
				$groupsFirstMembers[$group] = $groupFirstMemberPointer;
				$expectedGroup = $group;

				continue;
			}

			$expectedGroups = array_filter(
				$groupsOrder,
				static fn (int $order): bool => $order >= $groupsOrder[$expectedGroup],
			);
			$fix = $phpcsFile->addFixableError(
				sprintf(
					'The placement of "%s" group is invalid. Last group was "%s" and one of these is expected after it: %s',
					$group,
					$expectedGroup,
					implode(', ', array_keys($expectedGroups)),
				),
				$groupFirstMemberPointer,
				self::CODE_INCORRECT_GROUP_ORDER,
			);
			if (!$fix) {
				continue;
			}

			foreach ($groupsFirstMembers as $memberGroup => $firstMemberPointer) {
				if ($groupsOrder[$memberGroup] <= $groupsOrder[$group]) {
					continue;
				}

				$this->fixIncorrectGroupOrder($phpcsFile, $groupFirstMemberPointer, $groupLastMemberPointer, $firstMemberPointer);

				// run the sniff again to fix the rest of the groups
				return $pointer - 1;
			}
		}

		return $pointer + 1;
	}

	/**
	 * @param array{scope_closer: int, level: int} $rootScopeToken
	 * @return array{int, int, string}|null
	 */
	private function findNextGroup(File $phpcsFile, int $pointer, array $rootScopeToken): ?array
	{
		$tokens = $phpcsFile->getTokens();

		$currentTokenPointer = $pointer;
		while (true) {
			$currentTokenPointer = TokenHelper::findNext(
				$phpcsFile,
				[T_USE, T_ENUM_CASE, T_CONST, T_VARIABLE, T_FUNCTION],
				$currentTokenPointer + 1,
				$rootScopeToken['scope_closer'],
			);
			if ($currentTokenPointer === null) {
				break;
			}

			$currentToken = $tokens[$currentTokenPointer];

			if ($currentToken['code'] === T_VARIABLE && !PropertyHelper::isProperty($phpcsFile, $currentTokenPointer)) {
				continue;
			}

			if ($currentToken['level'] - $rootScopeToken['level'] !== 1) {
				continue;
			}

			$group = $this->getGroupForToken($phpcsFile, $currentTokenPointer);

			if (!isset($currentGroup)) {
				$currentGroup = $group;
				$groupFirstMemberPointer = $currentTokenPointer;
			}

			if ($group !== $currentGroup) {
				break;
			}

			$groupLastMemberPointer = $currentTokenPointer;

			$currentTokenPointer = $currentToken['code'] === T_VARIABLE
				// Skip to the end of the property definition
				? PropertyHelper::getEndPointer($phpcsFile, $currentTokenPointer)
				: ($currentToken['scope_closer'] ?? $currentTokenPointer);
		}

		if (!isset($currentGroup)) {
			return null;
		}

		assert(isset($groupFirstMemberPointer) === true);
		assert(isset($groupLastMemberPointer) === true);

		return [$groupFirstMemberPointer, $groupLastMemberPointer, $currentGroup];
	}

	private function getGroupForToken(File $phpcsFile, int $pointer): string
	{
		$tokens = $phpcsFile->getTokens();

		switch ($tokens[$pointer]['code']) {
			case T_USE:
				return self::GROUP_USES;
			case T_ENUM_CASE:
				return self::GROUP_ENUM_CASES;
			case T_CONST:
				switch ($this->getVisibilityForToken($phpcsFile, $pointer)) {
					case T_PUBLIC:
						return self::GROUP_PUBLIC_CONSTANTS;
					case T_PROTECTED:
						return self::GROUP_PROTECTED_CONSTANTS;
				}

				return self::GROUP_PRIVATE_CONSTANTS;
			case T_FUNCTION:
				$name = strtolower(FunctionHelper::getName($phpcsFile, $pointer));
				if (array_key_exists($name, self::SPECIAL_METHODS)) {
					return self::SPECIAL_METHODS[$name];
				}

				$methodGroup = $this->resolveMethodGroup($phpcsFile, $pointer, $name);

				if ($methodGroup !== null) {
					return $methodGroup;
				}

				$visibility = $this->getVisibilityForToken($phpcsFile, $pointer);
				$isStatic = $this->isMemberStatic($phpcsFile, $pointer);
				$isFinal = $this->isMethodFinal($phpcsFile, $pointer);

				if ($this->isMethodAbstract($phpcsFile, $pointer)) {
					if ($visibility === T_PUBLIC) {
						return $isStatic ? self::GROUP_PUBLIC_STATIC_ABSTRACT_METHODS : self::GROUP_PUBLIC_ABSTRACT_METHODS;
					}

					return $isStatic ? self::GROUP_PROTECTED_STATIC_ABSTRACT_METHODS : self::GROUP_PROTECTED_ABSTRACT_METHODS;
				}

				if ($isStatic && $visibility === T_PUBLIC && $this->isStaticConstructor($phpcsFile, $pointer)) {
					return self::GROUP_STATIC_CONSTRUCTORS;
				}

				switch ($visibility) {
					case T_PUBLIC:
						if ($isFinal) {
							return $isStatic ? self::GROUP_PUBLIC_STATIC_FINAL_METHODS : self::GROUP_PUBLIC_FINAL_METHODS;
						}

						return $isStatic ? self::GROUP_PUBLIC_STATIC_METHODS : self::GROUP_PUBLIC_METHODS;
					case T_PROTECTED:
						if ($isFinal) {
							return $isStatic ? self::GROUP_PROTECTED_STATIC_FINAL_METHODS : self::GROUP_PROTECTED_FINAL_METHODS;
						}

						return $isStatic ? self::GROUP_PROTECTED_STATIC_METHODS : self::GROUP_PROTECTED_METHODS;
				}

				return $isStatic ? self::GROUP_PRIVATE_STATIC_METHODS : self::GROUP_PRIVATE_METHODS;
			default:
				$isStatic = $this->isMemberStatic($phpcsFile, $pointer);
				$visibility = $this->getVisibilityForToken($phpcsFile, $pointer);

				switch ($visibility) {
					case T_PUBLIC:
					case T_PUBLIC_SET:
						return $isStatic ? self::GROUP_PUBLIC_STATIC_PROPERTIES : self::GROUP_PUBLIC_PROPERTIES;
					case T_PROTECTED:
						return $isStatic
							? self::GROUP_PROTECTED_STATIC_PROPERTIES
							: self::GROUP_PROTECTED_PROPERTIES;
					default:
						return $isStatic ? self::GROUP_PRIVATE_STATIC_PROPERTIES : self::GROUP_PRIVATE_PROPERTIES;
				}
		}
	}

	private function resolveMethodGroup(File $phpcsFile, int $pointer, string $method): ?string
	{
		foreach ($this->getNormalizedMethodGroups() as $group => $methodRequirements) {
			foreach ($methodRequirements as $methodRequirement) {
				if ($methodRequirement['name'] !== null) {
					$requiredName = strtolower($methodRequirement['name']);

					if (StringHelper::endsWith($requiredName, '*')) {
						$methodNamePrefix = substr($requiredName, 0, -1);

						if ($method === $methodNamePrefix || !StringHelper::startsWith($method, $methodNamePrefix)) {
							continue;
						}
					} elseif ($method !== $requiredName) {
						continue;
					}
				}

				if (
					$this->hasRequiredAnnotations($phpcsFile, $pointer, $methodRequirement['annotations'])
					&& $this->hasRequiredAttributes($phpcsFile, $pointer, $methodRequirement['attributes'])
				) {
					return $group;
				}
			}
		}

		return null;
	}

	/**
	 * @param array<string> $requiredAnnotations
	 */
	private function hasRequiredAnnotations(File $phpcsFile, int $pointer, array $requiredAnnotations): bool
	{
		if ($requiredAnnotations === []) {
			return true;
		}

		$annotations = [];

		foreach (AnnotationHelper::getAnnotations($phpcsFile, $pointer) as $annotation) {
			$annotations[$annotation->getName()] = true;
		}

		foreach ($requiredAnnotations as $requiredAnnotation) {
			if (!array_key_exists('@' . $requiredAnnotation, $annotations)) {
				return false;
			}
		}

		return true;
	}

	/**
	 * @param array<string> $requiredAttributes
	 */
	private function hasRequiredAttributes(File $phpcsFile, int $pointer, array $requiredAttributes): bool
	{
		if ($requiredAttributes === []) {
			return true;
		}

		$attributesClassNames = $this->getAttributeClassNamesForToken($phpcsFile, $pointer);

		foreach ($requiredAttributes as $requiredAttribute) {
			if (!array_key_exists(strtolower($requiredAttribute), $attributesClassNames)) {
				return false;
			}
		}

		return true;
	}

	/**
	 * @return array<string, string>
	 */
	private function getAttributeClassNamesForToken(File $phpcsFile, int $pointer): array
	{
		$tokens = $phpcsFile->getTokens();
		$attributePointer = null;
		$attributes = [];

		while (true) {
			$attributeEndPointerCandidate = TokenHelper::findPrevious(
				$phpcsFile,
				[T_ATTRIBUTE_END, T_SEMICOLON, T_CLOSE_CURLY_BRACKET, T_OPEN_CURLY_BRACKET],
				$attributePointer ?? $pointer - 1,
			);

			if (
				$attributeEndPointerCandidate === null
				|| $tokens[$attributeEndPointerCandidate]['code'] !== T_ATTRIBUTE_END
			) {
				break;
			}

			$attributePointer = $tokens[$attributeEndPointerCandidate]['attribute_opener'];

			foreach (AttributeHelper::getAttributes($phpcsFile, $attributePointer) as $attribute) {
				$attributeClass = NamespaceHelper::resolveClassName(
					$phpcsFile,
					$attribute->getName(),
					$attribute->getStartPointer(),
				);
				$attributeClass = ltrim($attributeClass, '\\');
				$attributes[strtolower($attributeClass)] = $attributeClass;
			}
		}

		return $attributes;
	}

	/**
	 * @return int|string
	 */
	private function getVisibilityForToken(File $phpcsFile, int $pointer)
	{
		$tokens = $phpcsFile->getTokens();

		$previousPointer = $pointer - 1;

		$endTokenCodes = [T_OPEN_CURLY_BRACKET, T_CLOSE_CURLY_BRACKET, T_SEMICOLON];
		$tokenCodesToSearch = [...array_values(Tokens::$scopeModifiers), ...$endTokenCodes];

		do {
			$previousPointer = TokenHelper::findPrevious($phpcsFile, $tokenCodesToSearch, $previousPointer - 1);

			if (in_array($tokens[$previousPointer]['code'], $endTokenCodes, true)) {
				// No visibility modifier found -> public
				return T_PUBLIC;
			}

			if (in_array($tokens[$previousPointer]['code'], [T_PROTECTED_SET, T_PRIVATE_SET], true)) {
				continue;
			}

			return $tokens[$previousPointer]['code'];

		} while (true);
	}

	private function isMemberStatic(File $phpcsFile, int $pointer): bool
	{
		$previousPointer = TokenHelper::findPrevious(
			$phpcsFile,
			[T_OPEN_CURLY_BRACKET, T_CLOSE_CURLY_BRACKET, T_SEMICOLON, T_STATIC],
			$pointer - 1,
		);
		return $phpcsFile->getTokens()[$previousPointer]['code'] === T_STATIC;
	}

	private function isMethodFinal(File $phpcsFile, int $pointer): bool
	{
		$previousPointer = TokenHelper::findPrevious(
			$phpcsFile,
			[T_OPEN_CURLY_BRACKET, T_CLOSE_CURLY_BRACKET, T_SEMICOLON, T_FINAL],
			$pointer - 1,
		);
		return $phpcsFile->getTokens()[$previousPointer]['code'] === T_FINAL;
	}

	private function isMethodAbstract(File $phpcsFile, int $pointer): bool
	{
		$previousPointer = TokenHelper::findPrevious(
			$phpcsFile,
			[T_OPEN_CURLY_BRACKET, T_CLOSE_CURLY_BRACKET, T_SEMICOLON, T_ABSTRACT],
			$pointer - 1,
		);
		return $phpcsFile->getTokens()[$previousPointer]['code'] === T_ABSTRACT;
	}

	private function isStaticConstructor(File $phpcsFile, int $pointer): bool
	{
		$parentClassName = $this->getParentClassName($phpcsFile, $pointer);

		$returnTypeHint = FunctionHelper::findReturnTypeHint($phpcsFile, $pointer);
		if ($returnTypeHint !== null) {
			return in_array($returnTypeHint->getTypeHintWithoutNullabilitySymbol(), ['self', $parentClassName], true);
		}

		$returnAnnotation = FunctionHelper::findReturnAnnotation($phpcsFile, $pointer);
		if ($returnAnnotation === null) {
			return false;
		}

		return in_array((string) $returnAnnotation->getValue()->type, ['static', 'self', $parentClassName], true);
	}

	private function getParentClassName(File $phpcsFile, int $pointer): string
	{
		$classPointer = TokenHelper::findPrevious($phpcsFile, Tokens::$ooScopeTokens, $pointer - 1);
		assert($classPointer !== null);

		return ClassHelper::getName($phpcsFile, $classPointer);
	}

	private function fixIncorrectGroupOrder(
		File $file,
		int $groupFirstMemberPointer,
		int $groupLastMemberPointer,
		int $nextGroupMemberPointer
	): void
	{
		$previousMemberEndPointer = $this->findPreviousMemberEndPointer($file, $groupFirstMemberPointer);

		$groupStartPointer = $this->findGroupStartPointer($file, $groupFirstMemberPointer, $previousMemberEndPointer);
		$groupEndPointer = $this->findGroupEndPointer($file, $groupLastMemberPointer);
		$groupContent = TokenHelper::getContent($file, $groupStartPointer, $groupEndPointer);

		$nextGroupMemberStartPointer = $this->findGroupStartPointer($file, $nextGroupMemberPointer);

		$file->fixer->beginChangeset();

		FixerHelper::removeBetweenIncluding($file, $groupStartPointer, $groupEndPointer);

		$linesBetween = $this->removeBlankLinesAfterMember($file, $previousMemberEndPointer, $groupStartPointer);

		$newLines = str_repeat($file->eolChar, $linesBetween);

		FixerHelper::addBefore($file, $nextGroupMemberStartPointer, $groupContent . $newLines);

		$file->fixer->endChangeset();
	}

	private function findPreviousMemberEndPointer(File $phpcsFile, int $memberPointer): int
	{
		$endTypes = [T_OPEN_CURLY_BRACKET, T_CLOSE_CURLY_BRACKET, T_SEMICOLON];
		$previousMemberEndPointer = TokenHelper::findPrevious($phpcsFile, $endTypes, $memberPointer - 1);
		assert($previousMemberEndPointer !== null);

		return $previousMemberEndPointer;
	}

	private function findGroupStartPointer(File $phpcsFile, int $memberPointer, ?int $previousMemberEndPointer = null): int
	{
		$startPointer = DocCommentHelper::findDocCommentOpenPointer($phpcsFile, $memberPointer - 1);
		if ($startPointer === null) {
			if ($previousMemberEndPointer === null) {
				$previousMemberEndPointer = $this->findPreviousMemberEndPointer($phpcsFile, $memberPointer);
			}

			$startPointer = TokenHelper::findNextEffective($phpcsFile, $previousMemberEndPointer + 1);
			assert($startPointer !== null);
		}

		$types = [T_OPEN_CURLY_BRACKET, T_CLOSE_CURLY_BRACKET, T_SEMICOLON];

		return (int) $phpcsFile->findFirstOnLine($types, $startPointer, true);
	}

	private function findGroupEndPointer(File $phpcsFile, int $memberPointer): int
	{
		$tokens = $phpcsFile->getTokens();

		if ($tokens[$memberPointer]['code'] === T_FUNCTION && !FunctionHelper::isAbstract($phpcsFile, $memberPointer)) {
			return $tokens[$memberPointer]['scope_closer'];
		}

		if ($tokens[$memberPointer]['code'] === T_USE && array_key_exists('scope_closer', $tokens[$memberPointer])) {
			return $tokens[$memberPointer]['scope_closer'];
		}

		$endPointer = TokenHelper::findNext($phpcsFile, [T_SEMICOLON, T_OPEN_CURLY_BRACKET], $memberPointer + 1);

		return $tokens[$endPointer]['code'] === T_OPEN_CURLY_BRACKET
			? $tokens[$endPointer]['bracket_closer']
			: $endPointer;
	}

	private function removeBlankLinesAfterMember(File $phpcsFile, int $memberEndPointer, int $endPointer): int
	{
		$whitespacePointer = $memberEndPointer;

		$linesToRemove = 0;
		while (true) {
			$whitespacePointer = TokenHelper::findNext($phpcsFile, T_WHITESPACE, $whitespacePointer, $endPointer);
			if ($whitespacePointer === null) {
				break;
			}

			$linesToRemove++;
			FixerHelper::replace($phpcsFile, $whitespacePointer, '');
			$whitespacePointer++;
		}

		return $linesToRemove;
	}

	/**
	 * @return array<string, list<array{name: string|null, attributes: array<string>, annotations: array<string>}>>
	 */
	private function getNormalizedMethodGroups(): array
	{
		if ($this->normalizedMethodGroups === null) {
			$this->normalizedMethodGroups = [];
			$methodGroups = SniffSettingsHelper::normalizeAssociativeArray($this->methodGroups);

			foreach ($methodGroups as $group => $groupDefinition) {
				$group = strtolower((string) $group);
				$this->normalizedMethodGroups[$group] = [];
				$methodDefinitions = preg_split('~\\s*,\\s*~', (string) $groupDefinition, -1, PREG_SPLIT_NO_EMPTY);
				/** @var list<non-empty-string> $methodDefinitions */
				foreach ($methodDefinitions as $methodDefinition) {
					$tokens = preg_split('~(?=[#@])~', $methodDefinition);
					/** @var non-empty-list<string> $tokens */
					$method = array_shift($tokens);
					$methodRequirement = [
						'name' => $method !== '' ? $method : null,
						'attributes' => [],
						'annotations' => [],
					];

					foreach ($tokens as $token) {
						$key = $token[0] === '#' ? 'attributes' : 'annotations';
						$methodRequirement[$key][] = substr($token, 1);
					}

					$this->normalizedMethodGroups[$group][] = $methodRequirement;
				}
			}
		}

		return $this->normalizedMethodGroups;
	}

	/**
	 * @return array<string, int>
	 */
	private function getNormalizedGroups(): array
	{
		if ($this->normalizedGroups === null) {
			$supportedGroups = [
				self::GROUP_USES,
				self::GROUP_ENUM_CASES,
				self::GROUP_PUBLIC_CONSTANTS,
				self::GROUP_PROTECTED_CONSTANTS,
				self::GROUP_PRIVATE_CONSTANTS,
				self::GROUP_PUBLIC_PROPERTIES,
				self::GROUP_PUBLIC_STATIC_PROPERTIES,
				self::GROUP_PROTECTED_PROPERTIES,
				self::GROUP_PROTECTED_STATIC_PROPERTIES,
				self::GROUP_PRIVATE_PROPERTIES,
				self::GROUP_PRIVATE_STATIC_PROPERTIES,
				self::GROUP_PUBLIC_STATIC_FINAL_METHODS,
				self::GROUP_PUBLIC_STATIC_ABSTRACT_METHODS,
				self::GROUP_PROTECTED_STATIC_FINAL_METHODS,
				self::GROUP_PROTECTED_STATIC_ABSTRACT_METHODS,
				self::GROUP_PUBLIC_FINAL_METHODS,
				self::GROUP_PUBLIC_ABSTRACT_METHODS,
				self::GROUP_PROTECTED_FINAL_METHODS,
				self::GROUP_PROTECTED_ABSTRACT_METHODS,
				self::GROUP_CONSTRUCTOR,
				self::GROUP_STATIC_CONSTRUCTORS,
				self::GROUP_DESTRUCTOR,
				self::GROUP_PUBLIC_METHODS,
				self::GROUP_PUBLIC_STATIC_METHODS,
				self::GROUP_PROTECTED_METHODS,
				self::GROUP_PROTECTED_STATIC_METHODS,
				self::GROUP_PRIVATE_METHODS,
				self::GROUP_PRIVATE_STATIC_METHODS,
				self::GROUP_MAGIC_METHODS,
			];

			$normalizedMethodGroups = $this->getNormalizedMethodGroups();
			$normalizedGroupsWithShortcuts = [];
			$order = 1;
			foreach (SniffSettingsHelper::normalizeArray($this->groups) as $groupsString) {
				/** @var list<non-empty-string> $groups */
				$groups = preg_split('~\\s*,\\s*~', strtolower($groupsString), -1, PREG_SPLIT_NO_EMPTY);
				foreach ($groups as $groupOrShortcut) {
					$groupOrShortcut = preg_replace('~\\s+~', ' ', $groupOrShortcut);

					if (
						!in_array($groupOrShortcut, $supportedGroups, true)
						&& !array_key_exists($groupOrShortcut, self::SHORTCUTS)
						&& $groupOrShortcut !== self::GROUP_INVOKE_METHOD
						&& !array_key_exists($groupOrShortcut, $normalizedMethodGroups)
					) {
						throw new UnsupportedClassGroupException($groupOrShortcut);
					}

					$normalizedGroupsWithShortcuts[$groupOrShortcut] = $order;
				}

				$order++;
			}

			$normalizedGroups = [];
			foreach ($normalizedGroupsWithShortcuts as $groupOrShortcut => $groupOrder) {
				if (
					in_array($groupOrShortcut, $supportedGroups, true)
					|| $groupOrShortcut === self::GROUP_INVOKE_METHOD
					|| array_key_exists($groupOrShortcut, $normalizedMethodGroups)
				) {
					$normalizedGroups[$groupOrShortcut] = $groupOrder;
				} else {
					foreach ($this->unpackShortcut($groupOrShortcut, $supportedGroups) as $group) {
						if (
							array_key_exists($group, $normalizedGroupsWithShortcuts)
							|| array_key_exists($group, $normalizedGroups)
						) {
							continue;
						}

						$normalizedGroups[$group] = $groupOrder;
					}
				}
			}

			if ($normalizedGroups === [] && $normalizedMethodGroups === []) {
				$normalizedGroups = array_flip($supportedGroups);
			} else {
				$missingGroups = array_diff(
					array_merge($supportedGroups, array_keys($normalizedMethodGroups)),
					array_keys($normalizedGroups),
				);

				if ($missingGroups !== []) {
					throw new MissingClassGroupsException(array_values($missingGroups));
				}
			}

			$this->normalizedGroups = $normalizedGroups;
		}

		return $this->normalizedGroups;
	}

	/**
	 * @param array<int, string> $supportedGroups
	 * @return array<int, string>
	 */
	private function unpackShortcut(string $shortcut, array $supportedGroups): array
	{
		$groups = [];

		foreach (self::SHORTCUTS[$shortcut] as $groupOrShortcut) {
			if (in_array($groupOrShortcut, $supportedGroups, true)) {
				$groups[] = $groupOrShortcut;
			} elseif (
				!array_key_exists($groupOrShortcut, self::SHORTCUTS)
				&& in_array($groupOrShortcut, self::SHORTCUTS[self::GROUP_SHORTCUT_FINAL_METHODS], true)
			) {
				// Nothing
			} else {
				$groups = array_merge($groups, $this->unpackShortcut($groupOrShortcut, $supportedGroups));
			}
		}

		return $groups;
	}

}
