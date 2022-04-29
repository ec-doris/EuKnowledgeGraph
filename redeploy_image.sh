docker buildx build --platform linux/amd64 . -t 294118183257.dkr.ecr.eu-west-1.amazonaws.com/linkedopendata-wikibase-bundle:1.35.5-wmde.3 -f wikibase-bundle.Dockerfile
aws ecr get-login-password --region eu-west-1 | docker login --username AWS --password-stdin 294118183257.dkr.ecr.eu-west-1.amazonaws.com
docker push 294118183257.dkr.ecr.eu-west-1.amazonaws.com/linkedopendata-wikibase-bundle:1.35.5-wmde.3
