# Release Strategy for Vizra ADK

This document outlines the release process and strategy for the Vizra ADK package.

## 📋 Table of Contents
- [Branching Strategy](#branching-strategy)
- [Versioning](#versioning)
- [Release Types](#release-types)
- [Release Process](#release-process)
- [Release Cycles](#release-cycles)
- [Hotfix Procedure](#hotfix-procedure)
- [Pre-release Versions](#pre-release-versions)
- [Release Checklist](#release-checklist)
- [Automation](#automation)

## 🌳 Branching Strategy

### Core Branches
- **master**: Production-ready code, all releases are tagged from here
- **develop** (recommended): Integration branch for upcoming releases
- **feature/***: New features and enhancements
- **hotfix/***: Critical fixes that bypass develop

### Branch Flow
```
feature/* → develop → master (tag release)
                ↑
           hotfix/* (critical only)
```

### Branch Protection Rules
- **master**: 
  - Require PR reviews
  - Require status checks (tests must pass)
  - No direct pushes (except hotfixes)
  
- **develop**:
  - Require tests to pass
  - Allow maintainer pushes

## 🏷️ Versioning

We follow [Semantic Versioning](https://semver.org/) (MAJOR.MINOR.PATCH):

### Version Increments
- **PATCH** (0.0.x): Bug fixes, security patches, documentation updates
  - No breaking changes
  - No new features
  - Example: Fix memory leak, update dependencies

- **MINOR** (0.x.0): New features, improvements
  - Backwards compatible
  - New tools, agents, or capabilities
  - Example: Add new embedding provider, new workflow type

- **MAJOR** (x.0.0): Breaking changes
  - API changes
  - Removal of features
  - Major architectural changes
  - Example: Change tool interface, remove deprecated methods

### Pre-1.0 Considerations
While in 0.x.x versions:
- API may change more frequently
- Use 0.x.0 for potentially breaking changes
- Communicate changes clearly in release notes

## 📦 Release Types

### 1. Regular Releases
Standard releases following the planned cycle.

### 2. Hotfix Releases
Critical fixes that can't wait for the next regular release.
- Security vulnerabilities
- Data corruption bugs
- Complete feature failures

### 3. Pre-release Versions
For testing new features with early adopters:
- **Alpha** (0.x.x-alpha.1): Internal testing, unstable
- **Beta** (0.x.x-beta.1): External testing, feature complete
- **RC** (0.x.x-rc.1): Release candidate, production ready

## 🚀 Release Process

### 1. Development Phase
```bash
# Create feature branch
git checkout -b feature/new-feature develop

# Work on feature
# ... make changes ...

# Push and create PR to develop
git push origin feature/new-feature
```

### 2. Integration Phase
```bash
# Merge features to develop
git checkout develop
git merge --no-ff feature/new-feature

# Run full test suite
composer test

# Run integration tests
php artisan test
```

### 3. Release Preparation
```bash
# Create release branch (optional for major/minor)
git checkout -b release/0.x.x develop

# Update version in composer.json
# Update CHANGELOG.md
# Run final tests
```

### 4. Release Execution
```bash
# Use the release script
./scripts/release.sh [patch|minor|major]

# Or manually:
git checkout master
git merge --no-ff release/0.x.x
git tag -a v0.x.x -m "Release v0.x.x"
git push origin master --tags
```

### 5. Post-release
```bash
# Merge back to develop
git checkout develop
git merge --no-ff master

# Delete release branch
git branch -d release/0.x.x
```

## 📅 Release Cycles

### Early Stage (Current - < 1.0.0)
- **Patch releases**: As needed for critical fixes (aim for within 48 hours)
- **Minor releases**: Bi-weekly or when features are ready
- **Communication**: Discord, GitHub discussions

### Growth Stage (1.0.0+)
- **Patch releases**: Within 1 week of discovery
- **Minor releases**: Monthly
- **Major releases**: Quarterly or bi-annually
- **LTS versions**: Consider after 2.0.0

### Release Schedule
- **Release Day**: Tuesdays (avoid Mondays and Fridays)
- **Release Time**: 2 PM UTC (good coverage for US/EU)
- **Announcement**: Within 24 hours on all channels

## 🚨 Hotfix Procedure

For critical issues that can't wait:

```bash
# 1. Create hotfix from master
git checkout -b hotfix/fix-critical master

# 2. Make the fix
# ... fix the issue ...

# 3. Test thoroughly
composer test

# 4. Merge to master and tag
git checkout master
git merge --no-ff hotfix/fix-critical
./scripts/release.sh patch

# 5. Merge to develop
git checkout develop
git merge --no-ff hotfix/fix-critical

# 6. Delete hotfix branch
git branch -d hotfix/fix-critical
```

## 🧪 Pre-release Versions

For testing new features with early adopters:

### Alpha Releases
```bash
# Tag as alpha
git tag -a v0.x.x-alpha.1 -m "Alpha release"

# In composer.json
"version": "0.x.x-alpha.1"
```

### Beta Releases
```bash
# After feature complete
git tag -a v0.x.x-beta.1 -m "Beta release"

# Get community feedback
# Fix issues, increment beta number
```

### Release Candidates
```bash
# When ready for production
git tag -a v0.x.x-rc.1 -m "Release candidate"

# If no issues for 1 week, promote to release
```

## ✅ Release Checklist

### Pre-release Checklist
- [ ] All tests passing (`composer test`)
- [ ] Documentation updated
- [ ] CHANGELOG.md updated with all changes
- [ ] Version bumped in composer.json
- [ ] Migration files reviewed
- [ ] Breaking changes documented
- [ ] Deprecations marked with @deprecated
- [ ] Security audit run (`composer audit`)
- [ ] Performance benchmarks acceptable

### Release Checklist
- [ ] Create release branch (if needed)
- [ ] Run release script: `./scripts/release.sh`
- [ ] Verify tag pushed to GitHub
- [ ] GitHub release created with notes
- [ ] Packagist webhook triggered (auto)
- [ ] Merge back to develop

### Post-release Checklist
- [ ] Announcement in Discord
- [ ] Update documentation site
- [ ] Tweet about release (optional)
- [ ] Monitor GitHub issues for problems
- [ ] Thank contributors in release notes

## 🤖 Automation

### Current Automation
- Release script (`scripts/release.sh`)
- Packagist auto-update on tag push

### Recommended Additions

#### GitHub Actions Workflow
Create `.github/workflows/release.yml`:
```yaml
name: Release

on:
  push:
    tags:
      - 'v*'

jobs:
  release:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v3
      
      - name: Run tests
        run: composer test
        
      - name: Create GitHub Release
        uses: softprops/action-gh-release@v1
        with:
          generate_release_notes: true
```

#### Pre-commit Hooks
For code quality:
```bash
# .git/hooks/pre-commit
#!/bin/bash
composer test
composer lint
```

## 📊 Release Metrics

Track these metrics to improve the release process:
- Time between releases
- Number of bugs per release
- Adoption rate of new versions
- Time to fix critical issues
- Community feedback response time

## 🔄 Continuous Improvement

### Quarterly Review
- Review release process effectiveness
- Analyze metrics
- Gather team feedback
- Update this document

### Community Feedback
- GitHub Issues labeled "release-process"
- Discord feedback channel
- Post-release surveys for major versions

## 📝 Communication Template

### Release Announcement Template
```markdown
## 🎉 Vizra ADK v0.x.x Released!

### ✨ Highlights
- Feature 1
- Feature 2
- Bug fixes

### 🔄 Upgrading
composer update vizra/vizra-adk

### 📚 Documentation
[Full changelog](link)
[Migration guide](link) (if applicable)

### 🙏 Contributors
Thanks to @user1, @user2

### 💬 Feedback
[GitHub Issues](link)
[Discord](link)
```

## 🆘 Emergency Contacts

For critical security issues:
- Security email: security@vizra.ai (set up when needed)
- Direct message maintainers on Discord
- Use GitHub Security Advisory (private)

---

## Quick Reference

### Common Commands
```bash
# Regular release
./scripts/release.sh patch|minor|major

# Manual version bump
composer version 0.x.x

# View recent tags
git tag -l "v*" --sort=-v:refname | head -10

# Check package status
composer show vizra/vizra-adk
```

### Version Decision Tree
```
Is it a breaking change? → MAJOR
Does it add new features? → MINOR  
Is it just fixes/docs? → PATCH
```

### Release Frequency Guidelines
- **Too Frequent**: User fatigue, upgrade burden
- **Too Infrequent**: Bugs persist, features delayed
- **Just Right**: Predictable, manageable, valuable

---

*Last updated: 2025-08-28*
*This is a living document. Update it as the project evolves.*