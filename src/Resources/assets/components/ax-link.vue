<script>
export default {
  name: "AxLink",
  props: {
    path: {
      type: String,
      require: true,
    },

  },

}
</script>

<template>
  <a href="#" :data-ax-link="path">
    <slot></slot>
  </a>
</template>