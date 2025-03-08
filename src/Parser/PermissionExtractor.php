<?php

namespace Sajjadhossainshohag\LaravelPermissionScanner\Parser;

use PhpParser\Node;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitorAbstract;
use PhpParser\ParserFactory;
use PhpParser\Error;

class PermissionExtractor
{
    public static function scan($path)
    {
        $results = [];
        $files = self::getPhpFiles($path);

        echo "Found " . count($files) . " PHP files to scan\n"; // Debug info

        $fileCount = 0;
        $errorCount = 0;
        $permissionsFound = [];

        foreach ($files as $file) {
            $fileCount++;

            try {
                $content = file_get_contents($file);
                if ($content === false) {
                    echo "Error reading file: $file\n";
                    $errorCount++;
                    continue;
                }

                // Check for patterns in the raw file content first (catches string literals)
                $simpleMatches = self::findPermissionPatternsInRawContent($content);
                if (!empty($simpleMatches)) {
                    $results[$file] = array_merge($results[$file] ?? [], $simpleMatches);
                    $permissionsFound = array_merge($permissionsFound, $simpleMatches);
                }

                // Then do the more sophisticated AST parsing
                $permissions = self::extractPermissions($content, $file);

                if (!empty($permissions)) {
                    $results[$file] = array_unique(array_merge($results[$file] ?? [], $permissions));
                    $permissionsFound = array_merge($permissionsFound, $permissions);
                }

                // Display progress every 100 files
                if ($fileCount % 100 === 0) {
                    echo "Processed $fileCount files...\n";
                }
            } catch (\Exception $e) {
                echo "Exception while processing $file: " . $e->getMessage() . "\n";
                $errorCount++;
            }
        }

        echo "Scan complete. Processed $fileCount files with $errorCount errors.\n";
        echo "Found permissions in " . count($results) . " files.\n";

        return $results;
    }

    private static function findPermissionPatternsInRawContent($content)
    {
        $permissions = [];

        // Pattern for permission middleware - standard format with string
        if (preg_match_all("/middleware\s*\(\s*['\"]permission:([^'\"]+)['\"]/", $content, $matches)) {
            foreach ($matches[1] as $match) {
                foreach (explode(',', $match) as $perm) {
                    $permissions[] = trim($perm);
                }
            }
        }

        // Pattern for permission middleware - class instantiation format
        if (preg_match_all("/new\s+Middleware\s*\(\s*['\"](permission:|\s*)([^'\"]+)['\"][\),]/", $content, $matches)) {
            foreach ($matches[2] as $match) {
                // Remove 'permission:' prefix if it's there
                $match = str_replace('permission:', '', $match);
                foreach (explode('|', $match) as $perm) {
                    foreach (explode(',', $perm) as $subPerm) {
                        $permissions[] = trim($subPerm);
                    }
                }
            }
        }

        // Pattern for can/cannot methods
        if (preg_match_all("/(can|cannot|hasPermissionTo|givePermissionTo|checkPermissionTo)\s*\(\s*['\"]([\w\.-]+)['\"][\),]/", $content, $matches)) {
            foreach ($matches[2] as $match) {
                $permissions[] = $match;
            }
        }

        // Pattern for Gate methods
        if (preg_match_all("/Gate::(allows|denies|check|authorize)\s*\(\s*['\"]([\w\.-]+)['\"][\),]/", $content, $matches)) {
            foreach ($matches[2] as $match) {
                $permissions[] = $match;
            }
        }

        // Pattern for Blade directives
        if (preg_match_all("/@(can|cannot|canany)\s*\(\s*['\"]([\w\.-]+)['\"][\),]/", $content, $matches)) {
            foreach ($matches[2] as $match) {
                $permissions[] = $match;
            }
        }

        return $permissions;
    }

    private static function getPhpFiles($path)
    {
        // Create an array to store all PHP files
        $files = [];

        try {
            // Check if path exists
            if (!file_exists($path)) {
                throw new \Exception("Path does not exist: $path");
            }

            // Use RecursiveDirectoryIterator to iterate through the directory and its subdirectories
            $directoryIterator = new \RecursiveDirectoryIterator(
                $path,
                \RecursiveDirectoryIterator::SKIP_DOTS | \RecursiveDirectoryIterator::FOLLOW_SYMLINKS
            );

            $iterator = new \RecursiveIteratorIterator(
                $directoryIterator,
                \RecursiveIteratorIterator::LEAVES_ONLY
            );

            // Iterate through each item found
            foreach ($iterator as $file) {
                // Check if the current item is a PHP file
                if ($file->isFile() && $file->getExtension() === 'php') {
                    // Add the file path to the files array
                    $files[] = $file->getRealPath();
                }
            }
        } catch (\Exception $e) {
            echo "Error scanning directory: " . $e->getMessage() . "\n";
        }

        return $files;
    }

    private static function extractPermissions($code, $filename = 'unknown')
    {
        $parser = (new ParserFactory())->createForNewestSupportedVersion();
        $traverser = new NodeTraverser();
        $visitor = new class extends NodeVisitorAbstract {
            public $permissions = [];
            public $currentClass = null;

            public function enterNode(Node $node)
            {
                // Track current class name
                if ($node instanceof Node\Stmt\Class_) {
                    if ($node->name) {
                        $this->currentClass = $node->name->name;
                    }
                }

                // Detect middleware permissions in various formats
                if ($node instanceof Node\Expr\MethodCall && $node->name instanceof Node\Identifier) {
                    $methodName = $node->name->name;

                    if ($methodName === 'middleware') {
                        $this->extractFromMiddleware($node->args);
                    } elseif (in_array($methodName, ['can', 'cannot', 'authorize'])) {
                        $this->extractFromCan($node->args);
                    } elseif (in_array($methodName, ['hasPermissionTo', 'givePermissionTo', 'checkPermissionTo', 'hasAnyPermission', 'hasAllPermissions'])) {
                        $this->extractFromPermissionMethods($node->args);
                    }
                }

                // Detect new Middleware instantiation pattern
                if (
                    $node instanceof Node\Expr\New_ &&
                    $node->class instanceof Node\Name &&
                    $node->class->toString() === 'Middleware'
                ) {

                    $this->extractFromMiddlewareClass($node->args);
                }

                // Detect Gate::allows() calls
                if (
                    $node instanceof Node\Expr\StaticCall &&
                    $node->class instanceof Node\Name &&
                    $node->name instanceof Node\Identifier
                ) {

                    $className = $node->class->toString();
                    $methodName = $node->name->name;

                    if (
                        $className === 'Gate' &&
                        in_array($methodName, ['allows', 'denies', 'check', 'authorize', 'any', 'none', 'before', 'after'])
                    ) {
                        $this->extractFromGate($node->args);
                    }
                }

                // Check for Blade directives in strings
                if ($node instanceof Node\Scalar\String_) {
                    $this->extractFromBladeStrings($node->value);
                }
            }

            private function extractFromMiddlewareClass(array $args)
            {
                if (isset($args[0]) && $args[0]->value instanceof Node\Scalar\String_) {
                    $value = $args[0]->value->value;

                    // Check if it starts with permission: or contains permission strings
                    if (strpos($value, 'permission:') === 0) {
                        $permStr = substr($value, 11);
                        $this->extractPermissionString($permStr);
                    } else {
                        // If it doesn't start with "permission:" but might contain permission strings
                        $this->extractPermissionString($value);
                    }
                }
            }

            private function extractPermissionString($permStr)
            {
                // Handle pipe-separated permissions first (permission1|permission2)
                foreach (explode('|', $permStr) as $pipePermission) {
                    // Then handle comma-separated permissions (permission1,permission2)
                    foreach (explode(',', $pipePermission) as $permission) {
                        $trimmed = trim($permission);
                        if (!empty($trimmed)) {
                            $this->permissions[] = $trimmed;
                        }
                    }
                }
            }

            private function extractFromMiddleware(array $args)
            {
                foreach ($args as $arg) {
                    if ($arg->value instanceof Node\Scalar\String_) {
                        // Check for permission middleware
                        if (strpos($arg->value->value, 'permission:') === 0) {
                            $permissionStr = substr($arg->value->value, 11);
                            $this->extractPermissionString($permissionStr);
                        } elseif (strpos($arg->value->value, 'role_or_permission:') === 0) {
                            $permissionStr = substr($arg->value->value, 19);
                            $this->extractPermissionString($permissionStr);
                        }
                    } elseif ($arg->value instanceof Node\Expr\Array_) {
                        // Handle array of middleware
                        foreach ($arg->value->items as $item) {
                            if ($item->value instanceof Node\Scalar\String_) {
                                if (strpos($item->value->value, 'permission:') === 0) {
                                    $permissionStr = substr($item->value->value, 11);
                                    $this->extractPermissionString($permissionStr);
                                } elseif (strpos($item->value->value, 'role_or_permission:') === 0) {
                                    $permissionStr = substr($item->value->value, 19);
                                    $this->extractPermissionString($permissionStr);
                                }
                            }
                        }
                    }
                }
            }

            private function extractFromCan(array $args)
            {
                if (isset($args[0]) && $args[0]->value instanceof Node\Scalar\String_) {
                    $this->permissions[] = $args[0]->value->value;
                }
            }

            private function extractFromPermissionMethods(array $args)
            {
                foreach ($args as $arg) {
                    if ($arg->value instanceof Node\Scalar\String_) {
                        $this->permissions[] = $arg->value->value;
                    } elseif ($arg->value instanceof Node\Expr\Array_) {
                        // Handle array of permissions
                        foreach ($arg->value->items as $item) {
                            if ($item->value instanceof Node\Scalar\String_) {
                                $this->permissions[] = $item->value->value;
                            }
                        }
                    }
                }
            }

            private function extractFromGate(array $args)
            {
                if (isset($args[0]) && $args[0]->value instanceof Node\Scalar\String_) {
                    $this->permissions[] = $args[0]->value->value;
                }
            }

            private function extractFromBladeStrings($value)
            {
                // Simple regex to find @can or @canany directives in string literals
                if (preg_match_all('/@(can|canany|cannot)\s*\(\s*[\'"]([^\'"]+)[\'"]/', $value, $matches)) {
                    foreach ($matches[2] as $permission) {
                        $this->permissions[] = $permission;
                    }
                }
            }
        };

        $traverser->addVisitor($visitor);

        try {
            $stmts = $parser->parse($code);
            if ($stmts) {
                $traverser->traverse($stmts);
            }
        } catch (Error $e) {
            // echo "Parse error in $filename: " . $e->getMessage() . "\n";
            return [];
        } catch (\Throwable $e) {
            // echo "Error processing $filename: " . $e->getMessage() . "\n";
            return [];
        }

        return $visitor->permissions;
    }

    // Debug method to enable manual running from command line
    public static function debug($path)
    {
        echo "Starting permission scan of: $path\n";
        $results = self::scan($path);

        $permissionCount = 0;
        foreach ($results as $file => $permissions) {
            $permissionCount += count($permissions);
            echo "File: $file\n";
            echo "  Permissions: " . implode(", ", $permissions) . "\n";
        }

        echo "Total: " . count($results) . " files with $permissionCount permissions found.\n";
        return $results;
    }
}
