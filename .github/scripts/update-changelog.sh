#!/bin/bash

# Check if readme.txt exists
if [ ! -f readme.txt ]; then
  echo "readme.txt file not found!"
  exit 1
fi

# Determine the latest changelog version in readme.txt
latest_version=$(sed -n 's/^= \([0-9]\+\.[0-9]\+\.[0-9]\+\) =$/\1/p' readme.txt | head -n 1)

if [ -z "$latest_version" ]; then
  echo "No latest version found in readme.txt!"
  exit 0
fi

# Get the latest changelog section from readme.txt
latest_section=$(sed -n "/^= $latest_version =$/,/^= [0-9]\+\.[0-9]\+\.[0-9]\+ =$/ { /^= [0-9]\+\.[0-9]\+\.[0-9]\+ =$/!p; }" readme.txt)
latest_section="= ${latest_version} =\n${latest_section}\n"

# Remove the latest changelog section from changelog.txt
sed -i "/^= $latest_version =$/,/^= [0-9]\+\.[0-9]\+\.[0-9]\+ =$/ {/^= $latest_version =$/d; /^= [0-9]\+\.[0-9]\+\.[0-9]\+ =$/!d;}" changelog.txt

# Append the latest changelog section from readme.txt to changelog.txt
{ echo -e "$latest_section"; cat changelog.txt; } > temp.txt && mv temp.txt changelog.txt

# Check if there are changes in changelog.txt
if git diff --quiet changelog.txt; then
  echo "No changes in changelog.txt."
  exit 0
fi

# Configure Git commit information
git config --global user.email "gha@hcaptcha.com"
git config --global user.name "hCaptcha GHA"

# Add, commit, and push changes
git add changelog.txt
git commit -m "Update changelog from readme.txt"

# Check if running in CI environment
if [ -n "$CI" ]; then
  # Push changes if in CI environment
  git push
  echo "Changelog successfully updated and pushed to the repository."
else
  echo "Changelog updated locally. Skipping push."
fi
