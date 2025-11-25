#!/usr/bin/env node
// scripts/bump-version.mjs
// Security: Safe command execution without injection vulnerabilities (CodeQL compliant)
import { spawnSync } from 'node:child_process';
import fs from 'node:fs';
import path from 'node:path';
import { platform } from 'node:os';

// Helper: Cross-platform safe spawn (prevents command injection)
function safeSpawn(command, args = [], options = {}) {
  const isWindows = platform() === 'win32';
  
  // On Windows, only use shell for npm (needed for .cmd), NOT for git
  const spawnOptions = {
    ...options,
    shell: isWindows && command === 'npm'
  };
  
  return spawnSync(command, args, spawnOptions);
}

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
  const gitCheck = safeSpawn('git', ['diff-index', '--quiet', 'HEAD', '--']);
  if (gitCheck.status !== 0) {
    console.warn('⚠️  Warning: You have uncommitted changes!');
    console.log('   Continuing anyway...\n');
  }

  // 2. Bump version in package.json
  // Using array arguments prevents command injection (CodeQL safe)
  console.log('📦 Updating package.json...');
  const versionResult = safeSpawn('npm', ['version', type, '--no-git-tag-version'], {
    encoding: 'utf-8'
  });

  if (versionResult.error) {
    throw new Error(`Failed to spawn npm: ${versionResult.error.message}`);
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
  
  // Git add
  safeSpawn('git', ['add', 'package.json', 'package-lock.json'], { 
    stdio: 'inherit'
  });

  // Git commit - message as single array element (safe)
  safeSpawn('git', ['commit', '-m', `chore: bump version to ${newVersion}`], { 
    stdio: 'inherit'
  });

  // Git tag
  safeSpawn('git', ['tag', '-a', `v${newVersion}`, '-m', `Release v${newVersion}`], { 
    stdio: 'inherit'
  });

  console.log('\n✨ Version bumped successfully!\n');
  console.log('Next steps:');
  console.log(`  1. Review changes: git log -1`);
  console.log(`  2. Push to remote: git push && git push --tags`);
  console.log(`  3. Build & deploy: npm run build\n`);

} catch (error) {
  console.error('\n❌ Error bumping version:', error.message);
  process.exit(1);
}
