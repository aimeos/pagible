<script>
  import { useDrawerStore } from '../stores'

  export default {
    props: {
      'content': {type: Array, required: true},
      'filter': {type: Object, required: true},
    },

    emits: ['update:filter'],

    setup() {
      const drawer = useDrawerStore()
      return { drawer }
    },

    data() {
      return {
        open: [0, 1, 2],
      }
    },

    methods: {
      active(item) {
        for(const key in item.value) {
          if(this.filter[key] !== item.value[key]) {
            return false
          }
        }
        return true
      },


      has(key) {
        return this.filter[key] !== null ? true : false
      },


      toggle(item) {
        for(const key in item.value) {
          this.filter[key] = item.value[key]
        }

        this.$emit('update:filter', this.filter)
      },
    }
  }
</script>

<template>
  <v-navigation-drawer v-model="drawer.aside" mobile-breakpoint="md" location="end">

    <v-list v-model:opened="open">
      <v-list-group v-for="(group, index) in content" :key="index" :value="index">
        <template v-slot:activator="{ props }">
          <v-list-item v-bind="props" :class="{active: has(group.key)}">{{ group.title || group.key }}</v-list-item>
        </template>

        <v-list-item v-for="(item, idx) in (group.items || [])"
          :active="active(item)"
          :key="idx"
          rounded="lg">
          <v-btn
            @click="toggle(item)"
            :prepend-icon="item.icon"
            variant="text"
          >{{ item.title }}</v-btn>
        </v-list-item>

      </v-list-group>
    </v-list>

  </v-navigation-drawer>
</template>

<style scoped>
  .v-navigation-drawer {
    border-top-left-radius: 8px;
  }

  .v-locale--is-rtl .v-navigation-drawer {
    border-top-left-radius: 0;
    border-top-right-radius: 8px;
  }

  .v-list-item .v-btn {
    text-transform: capitalize;
  }

  .v-list-item.active:before {
    color: rgb(var(--v-theme-warning));
    margin-inline-end: 4px;
    font-size: 150%;
    content: "•";
  }
</style>
