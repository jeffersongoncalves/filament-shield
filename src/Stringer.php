<?php

declare(strict_types=1);

namespace BezhanSalleh\FilamentShield;

use Closure;
use Illuminate\Support\Traits\Conditionable;

class Stringer
{
    use Conditionable;

    protected string $content;

    protected string $filePath;

    protected int $baseIndentLevel = 0; // Track the base indentation level

    protected int $currentIndentLevel = 0; // Track the current indentation level

    protected bool $addNewLine = false; // Track whether to add a new line

    public static function for(string $filePath): self
    {
        return new self($filePath);
    }

    public function __construct(string $filePath)
    {
        $this->filePath = $filePath;
        $this->content = file_get_contents($filePath) ?: '';
    }

    protected function findLine(string $needle): ?array
    {
        // Search for the needle and return the line and its indentation
        $startPos = strpos($this->content, $needle);
        if ($startPos === false) {
            return null; // Not found
        }

        // Get the start of the line and calculate indentation
        $lineStartPos = strrpos(substr($this->content, 0, $startPos), PHP_EOL) ?: 0;
        $lineEndPos = strpos($this->content, PHP_EOL, $startPos) ?: strlen($this->content);

        $line = substr($this->content, $lineStartPos, $lineEndPos - $lineStartPos);
        $indentation = preg_replace('/\S.*/', '', $line); // Capture indentation

        return [
            'start' => $lineStartPos,
            'end' => $lineEndPos,
            'indentation' => $indentation,
        ];
    }

    public function prepend(string $needle, string $contentToPrepend, bool $beforeBlock = false): self
    {
        if (! $this->contains($needle)) {
            return $this; // Needle not found
        }

        if ($beforeBlock) {
            // Find the starting position of the method
            $startPos = strpos($this->content, $needle);
            if ($startPos === false) {
                return $this; // Needle not found
            }

            // Find the opening parenthesis position
            $openingParenPos = strpos($this->content, '(', $startPos);
            if ($openingParenPos === false) {
                return $this; // No opening parenthesis found
            }

            // Find the closing parenthesis
            $closingParenPos = $this->findClosingParen($openingParenPos);
            if (is_null($closingParenPos)) {
                return $this; // No closing parenthesis found
            }

            // Get the line indentation
            $lineInfo = $this->findLine($needle);
            $indentation = $lineInfo['indentation'] . $this->getIndentation();

            // Format the new content based on the newLine flag
            $formattedReplacement = $this->addNewLine
                ? PHP_EOL . $indentation . trim($contentToPrepend)
                : $indentation . trim($contentToPrepend);

            $this->addNewLine = false; // Reset flag

            // Insert the formatted replacement before the opening parenthesis
            $this->content = substr_replace($this->content, $formattedReplacement, $openingParenPos, 0);
        } else {
            // Normal prepend logic
            if ($lineInfo = $this->findLine($needle)) {
                // Prepend the content with proper indentation
                $newContent = $lineInfo['indentation'] . $this->getIndentation() . trim($contentToPrepend);
                if ($this->addNewLine) {
                    $newContent = PHP_EOL . $newContent;
                    $this->addNewLine = false; // Reset the flag
                }
                $this->content = substr_replace(
                    $this->content,
                    $newContent,
                    $lineInfo['start'],
                    0
                );
            }
        }

        return $this;
    }

    public function append(string $needle, string $contentToAppend, bool $afterBlock = false): self
    {
        if (! $this->contains($needle)) {
            return $this; // Needle not found
        }

        if ($afterBlock) {
            // Find the starting position of the method
            $startPos = strpos($this->content, $needle);
            if ($startPos === false) {
                return $this; // Needle not found
            }

            // Find the opening parenthesis position
            $openingParenPos = strpos($this->content, '(', $startPos);
            if ($openingParenPos === false) {
                return $this; // No opening parenthesis found
            }

            // Find the closing parenthesis
            $closingParenPos = $this->findClosingParen($openingParenPos);
            if (is_null($closingParenPos)) {
                return $this; // No closing parenthesis found
            }

            // Get the line indentation
            $lineInfo = $this->findLine($needle);
            $indentation = $lineInfo['indentation'] . $this->getIndentation();

            // Format the new content based on the newLine flag
            $formattedReplacement = $this->addNewLine
                ? PHP_EOL . $indentation . trim($contentToAppend)
                : $indentation . trim($contentToAppend);

            $this->addNewLine = false; // Reset flag

            // If the closing parenthesis has a semicolon, move it to a new line with indentation
            if ($this->content[$closingParenPos + 1] === ';') {
                $this->content = substr_replace(
                    $this->content,
                    PHP_EOL . $indentation . ';',
                    $closingParenPos + 1,
                    0
                );
                $closingParenPos += strlen(PHP_EOL . $indentation . ';'); // Adjust closing position
            }

            // Insert the formatted replacement after the closing parenthesis
            $this->content = substr_replace($this->content, $formattedReplacement, $closingParenPos + 1, 0);
        } else {
            // Normal append logic
            if ($lineInfo = $this->findLine($needle)) {
                // Append the content with proper indentation
                $newContent = $lineInfo['indentation'] . $this->getIndentation() . trim($contentToAppend);
                if ($this->addNewLine) {
                    $newContent = PHP_EOL . $newContent;
                    $this->addNewLine = false; // Reset the flag
                }
                $this->content = substr_replace(
                    $this->content,
                    $newContent,
                    $lineInfo['end'],
                    0
                );
            }
        }

        return $this;
    }

    protected function findClosingParen(int $openingParenPos): ?int
    {
        $stack = 0;
        $length = strlen($this->content);

        for ($i = $openingParenPos; $i < $length; $i++) {
            if ($this->content[$i] === '(') {
                $stack++;
            } elseif ($this->content[$i] === ')') {
                $stack--;
                if ($stack === 0) {
                    return $i; // Found the closing parenthesis
                }
            }
        }

        return null; // Closing parenthesis not found
    }

    public function replace(string $needle, string $replacement): self
    {
        if ($lineInfo = $this->findLine($needle)) {
            // Replace the entire line containing the needle
            $this->content = substr_replace(
                $this->content,
                $lineInfo['indentation'] . trim($replacement),
                $lineInfo['start'],
                strlen(substr($this->content, $lineInfo['start'], $lineInfo['end'] - $lineInfo['start']))
            );
        }

        return $this;
    }

    public function newLine(): self
    {
        $this->addNewLine = true; // Set the flag to add a new line

        return $this;
    }

    public function indent(int $level): self
    {
        $this->currentIndentLevel += $level;

        return $this;
    }

    public function deindent(int $level): self
    {
        $this->currentIndentLevel = max(0, $this->currentIndentLevel - $level);

        return $this;
    }

    public function getIndentation(): string
    {
        return str_repeat(' ', $this->baseIndentLevel + $this->currentIndentLevel);
    }

    public function replaceFirst(string $needle, string $replacement): self
    {
        if ($lineInfo = $this->findLine($needle)) {
            $indentedReplacement = $lineInfo['indentation'] . trim($replacement);

            // Replace the first occurrence line using `start` and `end` positions
            $this->content = substr_replace(
                $this->content,
                $indentedReplacement,
                $lineInfo['start'],
                $lineInfo['end'] - $lineInfo['start']
            );
        }

        return $this;
    }

    public function replaceLast(string $needle, string $replacement): self
    {
        $lastPos = strrpos($this->content, $needle);
        if ($lastPos !== false) {
            // Use findLine based on the last occurrence's position
            $lineInfo = $this->findLine($needle);

            if ($lineInfo) {
                $indentedReplacement = $lineInfo['indentation'] . trim($replacement);

                // Replace the last occurrence with proper indentation
                $this->content = substr_replace(
                    $this->content,
                    $indentedReplacement,
                    $lineInfo['start'],
                    $lineInfo['end'] - $lineInfo['start']
                );
            }
        }

        return $this;
    }

    public function contains(string $needle): bool
    {
        // Check if the needle has a wildcard for partial matching
        $isPartialMatch = str_starts_with($needle, '*') || str_ends_with($needle, '*');

        if ($isPartialMatch) {
            // Strip the `*` characters for partial matching
            $needle = trim($needle, '*');

            return (bool) preg_match('/' . preg_quote($needle, '/') . '/', $this->content);
        } else {
            // Perform an exact search
            return strpos($this->content, $needle) !== false;
        }
    }

    public function save(): bool
    {
        return (bool) file_put_contents($this->filePath, $this->content);
    }

    public function getContent(): string
    {
        return $this->content;
    }

    // New chaining method that allows for indentations while performing operations
    public function execute(Closure $closure): self
    {
        $closure($this); // Call the closure, allowing operations to be performed

        return $this;
    }

    public function appendAfterLast(string $needle, string $replacement): self
    {
        $lastPos = strrpos($this->content, $needle);

        if ($lastPos !== false) {
            $insertPos = $lastPos + strlen($needle);

            $nextLinePos = strpos($this->content, PHP_EOL, $insertPos) ?: strlen($this->content);
            $followingLine = substr($this->content, $insertPos, $nextLinePos - $insertPos);
            preg_match('/^\s*/', $followingLine, $matches);
            $followingIndentation = $matches[0] ?? '';

            $formattedReplacement = trim($replacement);
            if ($this->addNewLine) {
                $formattedReplacement = PHP_EOL . $formattedReplacement . PHP_EOL;
            }

            $this->addNewLine = false;

            $this->content = substr_replace($this->content, $followingIndentation . $formattedReplacement, $insertPos, 0);
        }

        return $this;
    }

    public function prependBeforeLast(string $needle, string $replacement): self
    {
        $lastPos = strrpos($this->content, $needle);

        if ($lastPos !== false) {
            $lineStartPos = strrpos(substr($this->content, 0, $lastPos), PHP_EOL) ?: 0;
            $line = substr($this->content, $lineStartPos, $lastPos - $lineStartPos);

            preg_match('/^\s*/', $line, $matches);
            $originalIndentation = $matches[0] ?? '';

            $formattedReplacement = $this->getIndentation() . trim($replacement);
            if ($this->addNewLine) {
                $formattedReplacement = PHP_EOL . $formattedReplacement . PHP_EOL;
            }

            $this->addNewLine = false;

            $this->content = substr_replace($this->content, $originalIndentation . $formattedReplacement, $lineStartPos, 0);
        }

        return $this;
    }
}
