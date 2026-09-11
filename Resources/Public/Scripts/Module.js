(function () {
    'use strict';

    function nodeFieldName(tree) {
        var existing = tree.querySelector('input[type="checkbox"]');
        return existing ? existing.name : 'selectedNodes[]';
    }

    function renderChild(tree, child) {
        var item = document.createElement('li');
        item.className = 'neosacl-tree__item neosacl-tree__item--collapsed';
        item.dataset.aggregateId = child.aggregateId;
        item.dataset.loaded = 'false';

        var row = document.createElement('div');
        row.className = 'neosacl-tree__row';

        if (child.hasChildren) {
            var toggle = document.createElement('button');
            toggle.type = 'button';
            toggle.className = 'neosacl-tree__toggle';
            toggle.dataset.neosaclToggle = '';
            toggle.setAttribute('aria-expanded', 'false');
            toggle.innerHTML = '<i class="fas fa-chevron-right"></i>';
            row.appendChild(toggle);
        } else {
            var placeholder = document.createElement('span');
            placeholder.className = 'neosacl-tree__toggle neosacl-tree__toggle--placeholder';
            row.appendChild(placeholder);
        }

        var checkboxId = 'neosacl-node-' + child.aggregateId;
        var label = document.createElement('label');
        label.className = 'neos-checkbox';
        label.htmlFor = checkboxId;

        var checkbox = document.createElement('input');
        checkbox.type = 'checkbox';
        checkbox.id = checkboxId;
        checkbox.name = nodeFieldName(tree);
        checkbox.value = child.aggregateId;
        checkbox.checked = child.selected;
        label.appendChild(checkbox);
        label.appendChild(document.createElement('span'));
        label.appendChild(document.createTextNode(child.label + ' '));

        var type = document.createElement('small');
        type.className = 'neosacl-tree__type';
        type.textContent = child.nodeTypeName;
        label.appendChild(type);

        row.appendChild(label);
        item.appendChild(row);
        return item;
    }

    function loadChildren(tree, item) {
        var endpoint = tree.dataset.childrenEndpoint;
        var url = endpoint + (endpoint.indexOf('?') === -1 ? '?' : '&') + 'parentNodeAggregateId=' + encodeURIComponent(item.dataset.aggregateId);
        item.classList.add('neosacl-tree__item--loading');

        return fetch(url, { credentials: 'same-origin', headers: { Accept: 'application/json' } })
            .then(function (response) {
                if (!response.ok) {
                    throw new Error('Loading children failed with status ' + response.status);
                }
                return response.json();
            })
            .then(function (children) {
                var list = document.createElement('ul');
                list.className = 'neosacl-tree__children';
                children.forEach(function (child) {
                    list.appendChild(renderChild(tree, child));
                });
                item.appendChild(list);
                item.dataset.loaded = 'true';
            })
            .finally(function () {
                item.classList.remove('neosacl-tree__item--loading');
            });
    }

    function onToggle(tree, toggle) {
        var item = toggle.closest('.neosacl-tree__item');
        var expand = toggle.getAttribute('aria-expanded') !== 'true';
        var ready = item.dataset.loaded === 'true' ? Promise.resolve() : loadChildren(tree, item);

        ready.then(function () {
            item.classList.toggle('neosacl-tree__item--collapsed', !expand);
            toggle.setAttribute('aria-expanded', expand ? 'true' : 'false');
        }).catch(function (error) {
            console.error(error);
        });
    }

    document.addEventListener('click', function (event) {
        var toggle = event.target.closest('[data-neosacl-toggle]');
        if (!toggle) {
            return;
        }
        var tree = toggle.closest('[data-neosacl-tree]');
        if (tree) {
            onToggle(tree, toggle);
        }
    });
})();
