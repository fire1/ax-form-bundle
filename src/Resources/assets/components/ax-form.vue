<script>
import {jsonAttr} from "@app/services/utils";

export default {
    name: "AxForm",
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
        uiv: {
            type: Number,
            default: 4,
            require: false,
        },
        plugin: {
            type: String,
            default: 'ax-submit',
            require: false,
        },

    },
    data() {
        return {jsonData:null}
    },

    watch: {
        json: {
            immediate: true,
            handler(newValue) {
                this.jsonData = newValue ? jsonAttr(this.json) : null;
            },
        },

    },

    mounted() {
        this.$refs.link.addEventListener('form-ready', e => this.$emit('ready', e.detail));
    }
}
//
//  TODO HTML tag needs data-uiv="bs5" when is done
</script>

<template>
    <a href="#" @click.prevent :data-ax-form="path"

       :data-ax-plugin="plugin"
       v-bind:data-json="jsonData"
       data-uiv="bs5"
        ref="link">

        <slot></slot>

    </a>
</template>
