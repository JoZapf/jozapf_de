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
    console.log('   Commit your changes first for clean versioning.\n');
  }

  // 2. Bump version in package.json
  // Using spawnSync with array arguments prevents command injection
  console.log('📦 Updating package.json...');
  const versionResult = spawnSync('npm', ['version', type, '--no-git-tag-version'], {
    stdio: 'inherit',
    encoding: 'utf-8'
  });

  if (versionResult.status !== 0) {
    throw new Error('npm version command failed');
  }

  // 3. Read new version
  const pkgPath = path.join(process.cwd(), 'package.json');
  const pkg = JSON.parse(fs.readFileSync(pkgPath, 'utf-8'));
  const newVersion = pkg.version;

  console.log(`✅ New version: ${newVersion}\n`);

  // 4. Create Git commit and tag
  console.log('📝 Creating Git commit and tag...');
  
  spawnSync('git', ['add', 'package.json', 'package-lock.json'], { stdio: 'inherit' });
  
  spawnSync('git', ['commit', '-m', `chore: bump version to ${newVersion}`], { stdio: 'inherit' });
  
  spawnSync('git', ['tag', '-a', `v${newVersion}`, '-m', `Release v${newVersion}`], { stdio: 'inherit' });

  console.log('\n✨ Version bumped successfully!\n');
  console.log('Next steps:');
  console.log(`  1. Review changes: git log -1`);
  console.log(`  2. Push to remote: git push && git push --tags`);
  console.log(`  3. Build & deploy: npm run build\n`);

} catch (error) {
  console.error('\n❌ Error bumping version:', error.message);
  process.exit(1);
}
