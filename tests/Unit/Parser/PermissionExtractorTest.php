<?php

use Sajjadhossainshohag\LaravelPermissionScanner\Parser\PermissionExtractor;

beforeEach(function () {
    $this->testFilesPath = __DIR__.'/test-files';
    if (! file_exists($this->testFilesPath)) {
        mkdir($this->testFilesPath, 0777, true);
    }
});

afterEach(function () {
    if (is_dir($this->testFilesPath)) {
        $files = scandir($this->testFilesPath);
        foreach ($files as $file) {
            if ($file !== '.' && $file !== '..') {
                unlink($this->testFilesPath.'/'.$file);
            }
        }
        rmdir($this->testFilesPath);
    }
});

function createTestFile(string $filename, string $content): string
{
    $filepath = test()->testFilesPath.'/'.$filename;
    file_put_contents($filepath, $content);

    return $filepath;
}

test('scan finds permissions in middleware', function () {
    $testFile = createTestFile(
        'TestController.php',
        <<<'PHP'
        <?php
        class TestController
        {
            public function __construct()
            {
                $this->middleware('permission:edit-posts');
                $this->middleware(['permission:delete-posts,create-posts']);
            }
        }
        PHP
    );

    $results = PermissionExtractor::scan([$this->testFilesPath]);

    expect($results)
        ->toBeArray()
        ->toHaveCount(1)
        ->toHaveKey(realpath($testFile))
        ->and($results[realpath($testFile)])
        ->toHaveCount(3)
        ->toContainEqual('edit-posts', 'delete-posts', 'create-posts');
});

test('scan finds permissions in can methods', function () {
    $testFile = createTestFile(
        'TestPolicy.php',
        <<<'PHP'
        <?php
        class TestPolicy
        {
            public function update($user)
            {
                return $user->can('update-posts');
                $user->cannot('delete-posts');
                auth()->user()->can('auth-create-posts');
            }
        }
        PHP
    );

    $results = PermissionExtractor::scan([$this->testFilesPath]);
    $realpath = realpath($testFile);

    expect($results)
        ->toBeArray()
        ->toHaveCount(1)
        ->toHaveKey($realpath)
        ->and($results[$realpath])
        ->toHaveCount(3)
        ->toContainEqual('update-posts', 'delete-posts', 'auth-create-posts');
});

test('scan finds permissions in gate methods', function () {
    $testFile = createTestFile(
        'TestGate.php',
        <<<'PHP'
        <?php
        class TestController
        {
            public function check()
            {
                Gate::allows('publish-post');
                Gate::denies('moderate-comments');
                Gate::authorize('manage-users');
                Gate::check('view-dashboard');
            }
        }
        PHP
    );

    $results = PermissionExtractor::scan([$this->testFilesPath]);

    $realpath = realpath($testFile);

    expect($results)
        ->toBeArray()
        ->toHaveCount(1)
        ->toHaveKey($realpath)
        ->and($results[$realpath])
        ->and($results[$realpath])
        ->toHaveCount(4)
        ->toContain('publish-post')
        ->toContain('moderate-comments')
        ->toContain('manage-users')
        ->toContain('view-dashboard');
});

test('scan finds permissions in blade directives', function () {
    $testFile = createTestFile(
        'test.blade.php',
        <<<'PHP'
        @can("edit-profile")
        Edit Profile
        @endcan
        @cannot("delete-account")
        Cannot Delete
        @endcannot
        PHP
    );

    $results = PermissionExtractor::scan([$this->testFilesPath]);

    expect($results)->toBeArray()->toHaveCount(1)->toHaveKey(realpath($testFile))->and($results[realpath($testFile)])->toHaveCount(2)->toContainEqual('edit-profile', 'delete-account');
});

test('scan handles invalid files', function () {
    $testFile = createTestFile('InvalidSyntax.php', '<?php this is invalid PHP code;');

    $results = PermissionExtractor::scan([$this->testFilesPath]);

    expect($results)
        ->toBeArray()
        ->and($results[$testFile] ?? [])
        ->toBeEmpty();
});
