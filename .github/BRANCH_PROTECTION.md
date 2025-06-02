# Branch Protection Configuration for Laravel Agent ADK

This document explains how to set up branch protection rules for your repository to ensure that tests must pass before merging into main.

## GitHub Branch Protection Setup

1. Go to your repository on GitHub
2. Click on **Settings** tab
3. Click on **Branches** in the left sidebar
4. Click **Add rule** next to "Branch protection rules"
5. Configure the following settings:

### Branch Protection Rule Configuration

**Branch name pattern:** `main`

**Protect matching branches:**
- ✅ Require a pull request before merging
  - ✅ Require approvals: 1
  - ✅ Dismiss stale reviews when new commits are pushed
  - ✅ Require review from code owners (if you have a CODEOWNERS file)

- ✅ Require status checks to pass before merging
  - ✅ Require branches to be up to date before merging
  - **Required status checks:** (Add these as they appear after the first workflow run)
    - `test (8.1, 11.*)`
    - `test (8.1, 12.*)`
    - `test (8.2, 11.*)`
    - `test (8.2, 12.*)`
    - `test (8.3, 11.*)`
    - `test (8.3, 12.*)`
    - `Coverage`
    - `Security Audit`
    - `Code Quality`

- ✅ Require conversation resolution before merging
- ✅ Require signed commits (optional but recommended)
- ✅ Require linear history (optional)
- ✅ Do not allow bypassing the above settings

**Restrictions:**
- ✅ Restrict pushes that create files larger than 100 MB

## Alternative: Using GitHub CLI

If you prefer using the command line, you can set up branch protection using GitHub CLI:

```bash
# Install GitHub CLI first: https://cli.github.com/

# Create the branch protection rule
gh api repos/:owner/:repo/branches/main/protection \
  --method PUT \
  --field required_status_checks='{"strict":true,"checks":[{"context":"test (8.1, 11.*)"},{"context":"test (8.1, 12.*)"},{"context":"test (8.2, 11.*)"},{"context":"test (8.2, 12.*)"},{"context":"test (8.3, 11.*)"},{"context":"test (8.3, 12.*)"},{"context":"Coverage"},{"context":"Security Audit"},{"context":"Code Quality"}]}' \
  --field enforce_admins=true \
  --field required_pull_request_reviews='{"required_approving_review_count":1,"dismiss_stale_reviews":true}' \
  --field restrictions=null
```

## Notes

- The status check names might vary slightly. After your first workflow run, check the "Checks" tab of a pull request to see the exact names GitHub uses.
- You can adjust the number of required approvals based on your team size.
- Consider adding a CODEOWNERS file to automatically request reviews from specific people for certain files.
- The workflows will run on both pushes to main and pull requests targeting main.

## Workflow Features

The GitHub Actions workflow includes:

1. **Matrix Testing**: Tests against multiple PHP versions (8.1, 8.2, 8.3) and Laravel versions (11.*, 12.*)
2. **Coverage Reporting**: Generates test coverage reports and uploads to Codecov
3. **Security Auditing**: Checks for known security vulnerabilities in dependencies
4. **Code Quality**: Enforces PSR-12 coding standards and runs static analysis with PHPStan
5. **Dependency Listing**: Shows installed packages for debugging purposes

The workflow is optimized for your Laravel Agent ADK package structure and will run tests from the package directory using the correct Pest configuration.
