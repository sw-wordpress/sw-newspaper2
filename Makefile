.DEFAULT_GOAL := dist
.PHONY: release dist
# Usage: make release VERSION=1.2.3; commits version, atomically pushes branch and tag.
release: export SWNEWSPAPER2_RELEASE_VERSION = $(VERSION)
release:
	bash bin/release.sh

dist:
	bash bin/build-zip.sh "$(CURDIR)/dist"
