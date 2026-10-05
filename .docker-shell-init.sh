case $- in
    *i*)
        if [ -z "${BASH_VERSION:-}" ] && [ -x /bin/bash ]; then
            exec /bin/bash
        fi
        ;;
esac
