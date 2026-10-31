
# GitHub Operations Rules

## General Rules
- You may read and modify project files when requested.
- Never automatically stage, commit, or push changes.
- Only perform Git operations when I explicitly command you.
- Use the existing Git repository and configured remote.
- Never create a new GitHub repository without permission.
- Never force push or delete branches.

## When I Say "Add"
1. Run git status.
2. Review all changed files.
3. Stage only the files relevant to my request.
4. Never stage .env, credentials, or sensitive files.
5. Report which files were staged.
6. Do not commit or push.

## When I Say "Commit"
1. Check staged files using git diff --cached.
2. Check for secrets and unrelated changes.
3. Create a commit with a descriptive message.
4. Report the commit hash and message.
5. Do not push.

## When I Say "Push"
1. Check the current branch and remote.
2. Confirm which commits will be pushed.
3. Push only to the intended GitHub repository.
4. Never force push.
5. Report the result.

## When I Say "Add, Commit, and Push"
1. Review all modified files.
2. Stage only relevant, safe files.
3. Create a descriptive commit.
4. Push to the configured GitHub repository.
5. Verify the result.
6. Report the branch, commit hash, and push status.

## Safety
- Never run git reset --hard.
- Never run git clean -fd.
- Never use git push --force.
- Never amend commits without permission.
- Never overwrite other people's work.
- Stop and ask if there are merge conflicts.
- Never push to an unexpected remote or branch.
