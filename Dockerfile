FROM php:8.4

# git needed for Infection
RUN apt-get update \
    && apt-get -y install --no-install-recommends \
        git

ADD --chmod=0755 https://github.com/mlocati/docker-php-extension-installer/releases/latest/download/install-php-extensions /usr/local/bin/

RUN install-php-extensions @composer pcov

ARG USER_ID
ARG GROUP_ID

RUN groupadd --gid ${GROUP_ID} code \
    && useradd --create-home --shell /bin/bash --uid ${USER_ID} --gid code code

USER code
