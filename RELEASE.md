# Releasing Pickleball Ratings

This workflow outlines the standard process for cutting a new stable release for the plugin, following a pattern similar to WordPress Core by tagging the final release commit and then immediately bumping the version for ongoing development.

## 1. Prepare and Commit the Release Version (`X.Y.Z`) (Local)

This commit is the final state of the release. All changes and version numbers must be accurate at this stage. Tagging here ensures it's pushed along with the correct commit. Use an **annotated tag** (`-a`) for all official releases.

1. **Release PR:** Create a PR for the release branch.
2. **Version Check:** Verify that the `Version:` header in `pickleball-ratings.php`, the `version` in `package.json`, and the `Stable tag:` in `readme.txt` match the **new release version** (e.g., `1.2.2`).
3. **Metadata Check:** Verify that `Requires at least:`, `Requires PHP:`, and other metadata in `pickleball-ratings.php` are current.
4. **Update Changelog:** Add the new version and its changes to the `== Changelog ==` section in `readme.txt`, including an `== Upgrade Notice ==` if applicable. (Refer to past [releases](https://github.com/ironprogrammer/pickleball-ratings/releases) for examples.)

| Action | Git Command Example |
| :--- | :--- |
| **Release PR** | `git checkout -b release/v1.2.2` |
| **Stage Files** | `git add pickleball-ratings.php package.json readme.txt` |
| **Create Annotated Tag** | `git tag -a v1.2.2 -m "Release 1.2.2"` |

## 2. Commit and Push (Local & Remote)

Now that the version, changelog, and tag are prepared, commit the updates and push to GitHub, including the new release tag.

| Action | Git Command Example |
| :--- | :--- |
| **Commit Changes** | `git commit -m "Release 1.2.2"` |
| **Push Branch** | `git push origin v1.2.2 --follow-tags` |

> ⚠️ **Critical Step:** Unlike branches, **tags must be explicitly pushed** to the remote repository. The `--follow-tags` option ensures that tags attached to this commit are pushed automatically.

## 3. Create a GitHub Release (Remote)

Create a GitHub Release to provides formal documentation and asset management.

1. Navigate to the repository on GitHub.
2. Go to the **Releases** tab $\rightarrow$ **Draft a new release**.
3. Select the tag you just pushed (e.g. `v1.2.2`) from the "Choose a tag" dropdown.
4. Enter the release **Title**, e.g. "Version 1.2.2".
5. Use the updated changelog content in the **Description**, and tag collaborators as appropriate.
6. Run `npm run plugin-zip` locally and attach it to the release files.
7. Click **Publish release**.

## 4. Bump the Development Version (Post-Release)

Immediately after the release is live, bump the version number on the `trunk` branch. This ensures that any subsequent work reflects the version of the *next* expected release, preventing accidental use of the just-released version number for new development.

* **Bump Version:** Update the `Version:` header in `pickleball-ratings.php`, the `version` in `package.json`, and the `Stable tag:` in `readme.txt` to the **next development version** (e.g., `1.2.3`).

| Action | Git Command Example |
| :--- | :--- |
| **Update Files** | *Manually update version to next major/minor/patch.* |
| **Stage Files** | `git add pickleball-ratings.php package.json readme.txt` |
| **Commit Changes** | `git commit -m "Bump version to v1.2.3 for development"` |
| **Push Branch** | `git push origin trunk` |

## 5. wporg

Releases are finally approved via the [release management](https://wordpress.org/plugins/developers/releases/) page in the plugin directory.
