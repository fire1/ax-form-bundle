<script>
import {jsonAttr} from "@app/services/utils";

export default {
    name: "AxEdit",
    props: {
        path: {
            type: String,
            require: true,
        },
        json: {
            type: [String, Object],
            require: false,
            default: null,
        },

    },
    data() {
        return {jsonData: ''}
    },

    watch: {
        json: {
            immediate: true,
            handler(newValue) {
                this.jsonData = newValue ? jsonAttr(this.json) : '';
            },
        },

    },
    methods: {
        compileJson() {
            if(typeof this.json === 'function')
                this.jsonData = jsonAttr(this.json())
        },
    },
}
</script>

<template>
    <a href="#" @click.prevent="compileJson" :data-ax-form="path"
       :data-json="jsonData"
       data-uiv="bs5"
       data-ax-plugin="ax-submit">

        <slot></slot>

    </a>
</template>
