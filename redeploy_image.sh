docker buildx build --platform linux/amd64 . -t 891376987990.dkr.ecr.eu-west-1.amazonaws.com/linkedopendata-dev-wikibase-bundle:1.41.1-wmde.20 -f wikibase-bundle.Dockerfile
aws ecr get-login-password --region eu-west-1 | docker login --username AWS --password-stdin 891376987990.dkr.ecr.eu-west-1.amazonaws.com
docker push 891376987990.dkr.ecr.eu-west-1.amazonaws.com/linkedopendata-dev-wikibase-bundle:1.41.1-wmde.20
