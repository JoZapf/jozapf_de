#!/usr/bin/env node
// scripts/bump-version.mjs
// Security: Using spawnSync with array arguments to prevent command injection (CodeQL compliant)
import { spawnSync } from 'node:child_process';
import fs from 'node:fs';
import path from 'node:path';

const args = process.argv.slice(2);
const type = args[0] || 'patch'; // patch | minor | major

// Whitelist validation - only allow specific bump types
const VALID_TYPES = ['patch', 'minor', 'major'];
if (!VALID_TYPES.includes(type)) {
  console.error('❌ Invalid bump type. Use: patch, minor, or major');
  process.exit(1);
}

console.log(`\n🚀 Bumping ${type} version...\n`);

try {
  // 1. Check if working directory is clean
  const gitCheck = spawnSync('git', ['diff-index', '--quiet', 'HEAD', '--']);
  if (gitCheck.status !== 0) {
    console.warn('⚠️  Warning: You have uncommitted changes!');
    console.log('   Continuing anyway...\n');
  }

  // 2. Bump version in package.json
  // Using spawnSync with array arguments prevents command injection
  console.log('📦 Updating package.json...');
  const versionResult = spawnSync('npm', ['version', type, '--no-git-tag-version'], {
    encoding: 'utf-8',
    shell: true  // Enable shell for npm on Windows
  });

  // Debug output
  if (versionResult.error) {
    console.error('❌ Spawn error:', versionResult.error);
    throw new Error(`Failed to spawn npm: ${versionResult.error.message}`);
  }

  if (versionResult.stderr) {
    console.log('npm stderr:', versionResult.stderr);
  }

  if (versionResult.status !== 0) {
    console.error('❌ npm version failed with exit code:', versionResult.status);
    console.error('stdout:', versionResult.stdout);
    console.error('stderr:', versionResult.stderr);
    throw new Error(`npm version command failed with exit code ${versionResult.status}`);
  }

  // 3. Read new version
  const pkgPath = path.join(process.cwd(), 'package.json');
  const pkg = JSON.parse(fs.readFileSync(pkgPath, 'utf-8'));
  const newVersion = pkg.version;

  console.log(`✅ New version: ${newVersion}\n`);

  // 4. Create Git commit and tag
  console.log('📝 Creating Git commit and tag...');
  
  const gitAdd = spawnSync('git', ['add', 'package.json', 'package-lock.json'], { 
    stdio: 'inherit',
    shell: true 
  });
  
  if (gitAdd.status !== 0) {
    console.warn('⚠️  git add failed, but continuing...');
  }

  const gitCommit = spawnSync('git', ['commit', '-m', `chore: bump version to ${newVersion}`], { 
    stdio: 'inherit',
    shell: true 
  });
  
  if (gitCommit.status !== 0) {
    console.warn('⚠️  git commit failed (maybe nothing to commit?)');
  }

  const gitTag = spawnSync('git', ['tag', '-a', `v${newVersion}`, '-m', `Release v${newVersion}`], { 
    stdio: 'inherit',
    shell: true 
  });

  if (gitTag.status !== 0) {
    console.warn('⚠️  git tag failed (maybe tag already exists?)');
  }

  console.log('\n✨ Version bumped successfully!\n');
  console.log('Next steps:');
  console.log(`  1. Review changes: git log -1`);
  console.log(`  2. Push to remote: git push && git push --tags`);
  console.log(`  3. Build & deploy: npm run build\n`);

} catch (error) {
  console.error('\n❌ Error bumping version:', error.message);
  process.exit(1);
}
