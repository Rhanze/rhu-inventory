# Inactive Laravel scaffold workflow examples

These files preserve the workflows inherited from the Laravel skeleton. They are outside `.github/workflows`, so GitHub does not execute them. Issue, pull-request and changelog workflows reference Laravel's upstream organization, and the test workflow targets the skeleton's branch conventions.

The RHU application's verification commands and deployment procedure are documented in the root README and `docs/VERCEL_DEPLOYMENT.md`. Introduce a project-specific CI workflow separately after reviewing permissions, build steps, test configuration and deployment access.
