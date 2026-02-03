#!/usr/bin/env node

/**
 * Vizra ADK Documentation Migration Verification Tool
 *
 * This script verifies that the Mintlify documentation migration is complete
 * and correctly structured. Run it after migrating docs to catch any issues.
 *
 * Usage:
 *   node docs/scripts/verify-migration.js
 *   node docs/scripts/verify-migration.js --blade-path=../vizra-app/resources/views/docs
 */

const fs = require('fs');
const path = require('path');

class MigrationVerifier {
  constructor(options = {}) {
    this.mdxPath = options.mdxPath || path.join(__dirname, '..');
    this.adkSrcPath = options.adkSrcPath || path.join(__dirname, '../../src');
    this.issues = [];
    this.warnings = [];
    this.passed = [];
  }

  /**
   * Get all MDX files in the docs directory
   */
  getMdxFiles(dir = this.mdxPath, files = []) {
    const entries = fs.readdirSync(dir, { withFileTypes: true });

    for (const entry of entries) {
      const fullPath = path.join(dir, entry.name);
      if (entry.isDirectory() && !['scripts', 'snippets', 'node_modules'].includes(entry.name)) {
        this.getMdxFiles(fullPath, files);
      } else if (entry.isFile() && entry.name.endsWith('.mdx')) {
        files.push(fullPath);
      }
    }

    return files;
  }

  /**
   * Check all expected pages exist
   */
  verifyPageCoverage() {
    console.log('\n📄 Checking page coverage...');

    const expectedPages = [
      'index',
      'installation/getting-started',
      'installation/requirements',
      'installation/configuration',
      'concepts/architecture',
      'concepts/agents',
      'concepts/tools',
      'concepts/mcp-integration',
      'concepts/sessions-memory',
      'concepts/dynamic-prompts',
      'concepts/workflows',
      'concepts/vector-rag',
      'concepts/evaluations',
      'concepts/tracing',
      'concepts/web-dashboard',
      'api-reference/artisan-commands',
      'api-reference/agent-class',
      'api-reference/tool-class',
      'api-reference/workflow-class',
      'api-reference/evaluation-class',
      'api-reference/events',
      'api-reference/error-handling',
      'api-reference/providers',
      'api-reference/laravel-boost',
      'api-reference/openai-compatibility',
      'api-reference/tracing-class'
    ];

    let found = 0;
    let missing = 0;

    for (const page of expectedPages) {
      const mdxFile = path.join(this.mdxPath, `${page}.mdx`);
      if (fs.existsSync(mdxFile)) {
        found++;
        this.passed.push(`Page exists: ${page}.mdx`);
      } else {
        missing++;
        this.issues.push({
          type: 'missing-page',
          page: `${page}.mdx`,
          message: `Expected page not found: ${page}.mdx`
        });
      }
    }

    console.log(`   Found: ${found}/${expectedPages.length} pages`);
    if (missing > 0) {
      console.log(`   ❌ Missing: ${missing} pages`);
    }
  }

  /**
   * Check for unconverted Blade syntax in MDX files
   */
  verifyComponentConversion() {
    console.log('\n🔍 Checking for unconverted Blade syntax...');

    const mdxFiles = this.getMdxFiles();
    const bladePatterns = [
      { pattern: /<x-[a-z-]+/g, name: 'Blade components (<x-...)' },
      { pattern: /\{\{\s*route\(/g, name: 'Laravel routes ({{ route(...) }})' },
      { pattern: /@(section|extends|include)\(/g, name: 'Blade directives (@section, @extends, @include)' },
      { pattern: /@(if|foreach|for|while)\s*\(/g, name: 'Blade control structures (@if, @foreach)' },
      { pattern: /\{\{\s*\$[a-zA-Z]/g, name: 'Blade variables ({{ $var }})' }
    ];

    let cleanFiles = 0;
    let filesWithIssues = 0;

    for (const file of mdxFiles) {
      const content = fs.readFileSync(file, 'utf8');
      const relativePath = path.relative(this.mdxPath, file);
      let fileHasIssues = false;

      for (const { pattern, name } of bladePatterns) {
        const matches = content.match(pattern);
        if (matches) {
          fileHasIssues = true;
          this.issues.push({
            type: 'unconverted-blade',
            file: relativePath,
            pattern: name,
            matches: matches.slice(0, 3),
            message: `Found ${name} in ${relativePath}: ${matches.slice(0, 3).join(', ')}`
          });
        }
      }

      if (fileHasIssues) {
        filesWithIssues++;
      } else {
        cleanFiles++;
      }
    }

    console.log(`   Clean files: ${cleanFiles}`);
    if (filesWithIssues > 0) {
      console.log(`   ❌ Files with Blade syntax: ${filesWithIssues}`);
    }
  }

  /**
   * Verify docs.json navigation includes all pages
   */
  verifyNavigation() {
    console.log('\n🗺️  Checking navigation configuration...');

    const docsJsonPath = path.join(this.mdxPath, 'docs.json');

    if (!fs.existsSync(docsJsonPath)) {
      this.issues.push({
        type: 'missing-config',
        file: 'docs.json',
        message: 'docs.json configuration file not found'
      });
      console.log('   ❌ docs.json not found');
      return;
    }

    try {
      const docsJson = JSON.parse(fs.readFileSync(docsJsonPath, 'utf8'));
      const navPages = this.extractPagesFromNav(docsJson.navigation);

      // Get all MDX files (excluding snippets)
      const mdxFiles = this.getMdxFiles()
        .map(f => path.relative(this.mdxPath, f).replace('.mdx', ''))
        .filter(f => !f.startsWith('snippets/'));

      let inNav = 0;
      let notInNav = 0;

      for (const mdx of mdxFiles) {
        if (navPages.includes(mdx)) {
          inNav++;
        } else {
          notInNav++;
          this.warnings.push({
            type: 'not-in-nav',
            file: mdx,
            message: `Page not in navigation: ${mdx}.mdx`
          });
        }
      }

      console.log(`   Pages in navigation: ${inNav}`);
      if (notInNav > 0) {
        console.log(`   ⚠️  Pages not in navigation: ${notInNav}`);
      }
    } catch (e) {
      this.issues.push({
        type: 'invalid-json',
        file: 'docs.json',
        message: `Invalid JSON in docs.json: ${e.message}`
      });
      console.log(`   ❌ Invalid JSON: ${e.message}`);
    }
  }

  /**
   * Extract page paths from navigation config
   */
  extractPagesFromNav(nav, pages = []) {
    if (nav && nav.groups) {
      for (const group of nav.groups) {
        if (group.pages) {
          pages.push(...group.pages);
        }
      }
    }
    return pages;
  }

  /**
   * Verify MDX frontmatter is valid
   */
  verifyFrontmatter() {
    console.log('\n📋 Checking MDX frontmatter...');

    const mdxFiles = this.getMdxFiles();
    let validFrontmatter = 0;
    let invalidFrontmatter = 0;

    for (const file of mdxFiles) {
      const content = fs.readFileSync(file, 'utf8');
      const relativePath = path.relative(this.mdxPath, file);

      // Check for frontmatter
      const frontmatterMatch = content.match(/^---\n([\s\S]*?)\n---/);

      if (!frontmatterMatch) {
        invalidFrontmatter++;
        this.issues.push({
          type: 'missing-frontmatter',
          file: relativePath,
          message: `Missing frontmatter in ${relativePath}`
        });
        continue;
      }

      // Check for required fields
      const frontmatter = frontmatterMatch[1];
      const hasTitle = /title:\s*["']?.+["']?/i.test(frontmatter);
      const hasDescription = /description:\s*["']?.+["']?/i.test(frontmatter);

      if (!hasTitle || !hasDescription) {
        invalidFrontmatter++;
        this.warnings.push({
          type: 'incomplete-frontmatter',
          file: relativePath,
          message: `Incomplete frontmatter in ${relativePath} (missing: ${!hasTitle ? 'title' : ''} ${!hasDescription ? 'description' : ''})`
        });
      } else {
        validFrontmatter++;
      }
    }

    console.log(`   Valid frontmatter: ${validFrontmatter}`);
    if (invalidFrontmatter > 0) {
      console.log(`   ❌ Issues found: ${invalidFrontmatter}`);
    }
  }

  /**
   * Check ADK Artisan commands are documented
   */
  verifyArtisanCommands() {
    console.log('\n⚡ Checking Artisan command documentation...');

    const commandsPath = path.join(this.adkSrcPath, 'Console/Commands');
    const artisanDocPath = path.join(this.mdxPath, 'api-reference/artisan-commands.mdx');

    if (!fs.existsSync(commandsPath)) {
      this.warnings.push({
        type: 'src-not-found',
        path: commandsPath,
        message: 'ADK source Commands directory not found - skipping command verification'
      });
      console.log('   ⚠️  Source directory not found, skipping');
      return;
    }

    if (!fs.existsSync(artisanDocPath)) {
      this.issues.push({
        type: 'missing-page',
        file: 'api-reference/artisan-commands.mdx',
        message: 'Artisan commands documentation page not found'
      });
      console.log('   ❌ Documentation page not found');
      return;
    }

    const artisanDoc = fs.readFileSync(artisanDocPath, 'utf8');
    const commandFiles = fs.readdirSync(commandsPath)
      .filter(f => f.endsWith('.php') && f !== 'stubs');

    let documented = 0;
    let undocumented = 0;

    for (const cmdFile of commandFiles) {
      const content = fs.readFileSync(path.join(commandsPath, cmdFile), 'utf8');
      const signatureMatch = content.match(/\$signature\s*=\s*['"]vizra:([^'"]+)['"]/);

      if (signatureMatch) {
        const commandName = `vizra:${signatureMatch[1].split(' ')[0]}`;
        if (artisanDoc.includes(commandName) || artisanDoc.toLowerCase().includes(commandName.replace('vizra:', ''))) {
          documented++;
        } else {
          undocumented++;
          this.warnings.push({
            type: 'undocumented-command',
            command: commandName,
            message: `Command may not be documented: ${commandName}`
          });
        }
      }
    }

    console.log(`   Documented commands: ${documented}`);
    if (undocumented > 0) {
      console.log(`   ⚠️  Potentially undocumented: ${undocumented}`);
    }
  }

  /**
   * Check ADK events are documented
   */
  verifyEvents() {
    console.log('\n📡 Checking event documentation...');

    const eventsPath = path.join(this.adkSrcPath, 'Events');
    const eventsDocPath = path.join(this.mdxPath, 'api-reference/events.mdx');

    if (!fs.existsSync(eventsPath)) {
      this.warnings.push({
        type: 'src-not-found',
        path: eventsPath,
        message: 'ADK source Events directory not found - skipping event verification'
      });
      console.log('   ⚠️  Source directory not found, skipping');
      return;
    }

    if (!fs.existsSync(eventsDocPath)) {
      this.issues.push({
        type: 'missing-page',
        file: 'api-reference/events.mdx',
        message: 'Events documentation page not found'
      });
      console.log('   ❌ Documentation page not found');
      return;
    }

    const eventsDoc = fs.readFileSync(eventsDocPath, 'utf8');
    const eventFiles = fs.readdirSync(eventsPath)
      .filter(f => f.endsWith('.php'));

    let documented = 0;
    let undocumented = 0;

    for (const eventFile of eventFiles) {
      const eventName = path.basename(eventFile, '.php');
      if (eventsDoc.includes(eventName)) {
        documented++;
      } else {
        undocumented++;
        this.warnings.push({
          type: 'undocumented-event',
          event: eventName,
          message: `Event may not be documented: ${eventName}`
        });
      }
    }

    console.log(`   Documented events: ${documented}`);
    if (undocumented > 0) {
      console.log(`   ⚠️  Potentially undocumented: ${undocumented}`);
    }
  }

  /**
   * Check for broken internal links
   */
  verifyInternalLinks() {
    console.log('\n🔗 Checking internal links...');

    const mdxFiles = this.getMdxFiles();
    let validLinks = 0;
    let brokenLinks = 0;

    const linkPattern = /\[([^\]]+)\]\(\/([^)]+)\)/g;

    for (const file of mdxFiles) {
      const content = fs.readFileSync(file, 'utf8');
      const relativePath = path.relative(this.mdxPath, file);
      let match;

      while ((match = linkPattern.exec(content)) !== null) {
        const linkPath = match[2].split('#')[0]; // Remove anchor
        const targetFile = path.join(this.mdxPath, `${linkPath}.mdx`);

        if (fs.existsSync(targetFile)) {
          validLinks++;
        } else {
          brokenLinks++;
          this.warnings.push({
            type: 'broken-link',
            file: relativePath,
            link: `/${linkPath}`,
            message: `Broken link in ${relativePath}: /${linkPath}`
          });
        }
      }
    }

    console.log(`   Valid links: ${validLinks}`);
    if (brokenLinks > 0) {
      console.log(`   ⚠️  Potentially broken links: ${brokenLinks}`);
    }
  }

  /**
   * Run all verification checks
   */
  run() {
    console.log('═══════════════════════════════════════════════════════════');
    console.log('  Vizra ADK Documentation Migration Verification');
    console.log('═══════════════════════════════════════════════════════════');
    console.log(`\nDocs path: ${this.mdxPath}`);
    console.log(`ADK source: ${this.adkSrcPath}`);

    this.verifyPageCoverage();
    this.verifyComponentConversion();
    this.verifyNavigation();
    this.verifyFrontmatter();
    this.verifyArtisanCommands();
    this.verifyEvents();
    this.verifyInternalLinks();

    // Summary
    console.log('\n═══════════════════════════════════════════════════════════');
    console.log('  Summary');
    console.log('═══════════════════════════════════════════════════════════');

    if (this.issues.length === 0 && this.warnings.length === 0) {
      console.log('\n✅ All checks passed! Documentation migration looks complete.\n');
      return 0;
    }

    if (this.issues.length > 0) {
      console.log(`\n❌ Found ${this.issues.length} issue(s) that should be fixed:\n`);
      for (const issue of this.issues) {
        console.log(`   [${issue.type}] ${issue.message}`);
      }
    }

    if (this.warnings.length > 0) {
      console.log(`\n⚠️  Found ${this.warnings.length} warning(s) to review:\n`);
      for (const warning of this.warnings) {
        console.log(`   [${warning.type}] ${warning.message}`);
      }
    }

    // Write report to file
    const report = {
      timestamp: new Date().toISOString(),
      docsPath: this.mdxPath,
      adkSrcPath: this.adkSrcPath,
      issues: this.issues,
      warnings: this.warnings,
      passed: this.passed.length
    };

    const reportPath = path.join(this.mdxPath, 'migration-report.json');
    fs.writeFileSync(reportPath, JSON.stringify(report, null, 2));
    console.log(`\n📝 Full report written to: ${reportPath}\n`);

    return this.issues.length > 0 ? 1 : 0;
  }
}

// Parse command line arguments
const args = process.argv.slice(2);
const options = {};

for (const arg of args) {
  if (arg.startsWith('--mdx-path=')) {
    options.mdxPath = arg.split('=')[1];
  } else if (arg.startsWith('--adk-src=')) {
    options.adkSrcPath = arg.split('=')[1];
  } else if (arg === '--help' || arg === '-h') {
    console.log(`
Vizra ADK Documentation Migration Verification Tool

Usage:
  node verify-migration.js [options]

Options:
  --mdx-path=PATH    Path to Mintlify docs directory (default: ../docs)
  --adk-src=PATH     Path to ADK source directory (default: ../../src)
  --help, -h         Show this help message

Examples:
  node verify-migration.js
  node verify-migration.js --mdx-path=/path/to/docs --adk-src=/path/to/src
`);
    process.exit(0);
  }
}

const verifier = new MigrationVerifier(options);
process.exit(verifier.run());
