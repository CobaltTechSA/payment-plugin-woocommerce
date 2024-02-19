# !/bin/bash


NAME=cbo-payment-gateway
VERSION=$(git describe --tags $(git rev-list --tags --max-count=1))
DEPLOY_DIR=$(pwd)/deploy/$NAME
ZIP_FILE=$DEPLOY_DIR-$VERSION.zip

rm -rf $ZIP_FILE

if [ ! -d "$DEPLOY_DIR" ]; then
  mkdir -p $DEPLOY_DIR
fi

rsync -av --exclude='deploy' --exclude='.git' --exclude='.idea' --exclude='localfiles' --exclude='deploy.sh' ./ $DEPLOY_DIR

cd $DEPLOY_DIR/..

zip -r $ZIP_FILE $NAME
rm -rf $DEPLOY_DIR

echo "Plugin file: $ZIP_FILE"