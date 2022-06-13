DIR=integration_nuiteq
rm -rf $DIR $DIR.dev.tar.gz
mkdir -p $DIR

rsync -a \
	--exclude=.git \
	--exclude=appinfo/signature.json \
	--exclude=*.swp \
	--exclude=build \
	--exclude=.gitignore \
	--exclude=.travis.yml \
	--exclude=.scrutinizer.yml \
	--exclude=CONTRIBUTING.md \
	--exclude=composer.json \
	--exclude=composer.lock \
	--exclude=composer.phar \
	--exclude=package.json \
	--exclude=package-lock.json \
	--exclude=js/node_modules \
	--exclude=node_modules \
	--exclude=src \
	--exclude=translationfiles \
	--exclude=webpack.* \
	--exclude=.eslintrc.js \
	--exclude=stylelint.config.js \
	--exclude=.github \
	--exclude=.gitlab-ci.yml \
	--exclude=crowdin.yml \
	--exclude=tools \
	--exclude=l10n/.tx \
	--exclude=l10n/l10n.pl \
	--exclude=l10n/templates \
	--exclude=l10n/*.sh \
	--exclude=l10n/[a-z][a-z] \
	--exclude=l10n/[a-z][a-z]_[A-Z][A-Z] \
	--exclude=l10n/no-php \
	--exclude=makefile \
	--exclude=screenshots \
	--exclude=phpunit*xml \
	--exclude=tests \
	--exclude=ci \
	--exclude=vendor/bin \
	--exclude=/$DIR \
	--exclude=/$DIR.dev.tar* \
	--exclude=/.idea \
	--exclude=/.editorconfig \
	--exclude=/.nextcloudignore \
	--exclude=/.php* \
	--exclude=/babel* \
	--exclude=/krank* \
	--exclude=/.tx \
	--exclude=/.l10nignore \
	--exclude=/release* \
	. $DIR

tar -czf $DIR.dev.tar.gz $DIR
rm -rf $DIR
